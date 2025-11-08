<?php

// src/Controller/DeliverableController.php - COMPLETE DELIVERABLES SYSTEM

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\MediaAsset;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Service\R2Storage;
use App\Service\EmailService;
use App\Service\StripePayoutService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ZipArchive;

#[Route('/api/deliverables')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DeliverableController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly R2Storage $r2,
        private readonly EmailService $emailService,
        private readonly StripePayoutService $stripePayoutService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * ✅ Creator uploads deliverable files for a booking
     * POST /api/deliverables/upload
     * Form-data: file, bookingId
     */
    #[Route('/upload', name: 'deliverable_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $file = $request->files->get('file');
        $bookingId = $request->request->get('bookingId');

        if (!$file || !$bookingId) {
            return $this->errorResponse(
                'file and bookingId are required',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Get booking
        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return $this->notFoundResponse('Booking not found');
        }

        // Verify user is the creator
        if ($booking->getCreator()->getId() !== $user->getId()) {
            return $this->forbiddenResponse('Only the creator can upload deliverables');
        }

        // Verify booking is completed or confirmed
        if (!in_array($booking->getStatus(), ['confirmed', 'completed'])) {
            return $this->errorResponse(
                'Can only upload deliverables for confirmed or completed bookings',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Upload to R2
        $prefix = "deliverables/{$bookingId}";
        $put = $this->r2->upload($file, $prefix);

        // Create MediaAsset
        $asset = (new MediaAsset())
            ->setOwner($user)
            ->setPurpose(MediaAsset::PURPOSE_BOOKING_DELIVERABLE)
            ->setStorageKey($put['key'])
            ->setPublicUrl($put['url'] ?: null)
            ->setFilename($file->getClientOriginalName())
            ->setContentType($file->getClientMimeType() ?? 'application/octet-stream')
            ->setBytes($file->getSize() ?? 0)
            ->setBooking($booking);

        $this->em->persist($asset);
        $this->em->flush();

        return $this->createdResponse([
            'id' => $asset->getId(),
            'filename' => $asset->getFilename(),
            'publicUrl' => $asset->getPublicUrl(),
            'bytes' => $asset->getBytes(),
            'uploadedAt' => $asset->getCreatedAt()->format(\DATE_ATOM),
        ], 'File uploaded successfully');
    }

    /**
     * ✅ Get all deliverables for a booking
     * GET /api/deliverables/booking/{id}
     */
    #[Route('/booking/{id}', name: 'deliverable_list', methods: ['GET'])]
    public function list(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingRepository->find($id);
        if (!$booking) {
            return $this->notFoundResponse('Booking not found');
        }

        // Verify user is participant
        if (
            $booking->getCreator()->getId() !== $user->getId() &&
            $booking->getAthlete()->getId() !== $user->getId()
        ) {
            return $this->forbiddenResponse('You are not part of this booking');
        }

        $deliverables = $booking->getDeliverables();

        $data = array_map(function (MediaAsset $asset) {
            return [
                'id' => $asset->getId(),
                'filename' => $asset->getFilename(),
                'publicUrl' => $asset->getPublicUrl(),
                'thumbnailUrl' => $asset->getThumbnailUrl(),
                'bytes' => $asset->getBytes(),
                'contentType' => $asset->getContentType(),
                'type' => $asset->getType(),
                'uploadedAt' => $asset->getCreatedAt()->format(\DATE_ATOM),
            ];
        }, $deliverables->toArray());

        return $this->createApiResponse([
            'bookingId' => $booking->getId(),
            'deliverables' => $data,
            'totalFiles' => count($data),
            'totalBytes' => array_sum(array_column($data, 'bytes')),
            'downloadRequested' => $booking->getDeliverableDownloadRequestedAt() !== null,
            'downloadedAt' => $booking->getDeliverableDownloadedAt()?->format(\DATE_ATOM),
            'payoutCompleted' => $booking->getPayoutCompletedAt() !== null,
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Athlete requests download link
     * POST /api/deliverables/request-download/{bookingId}
     * Generates ZIP, sends email with signed URL
     */
    #[Route('/request-download/{id}', name: 'deliverable_request', methods: ['POST'])]
    public function requestDownload(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingRepository->find($id);
        if (!$booking) {
            return $this->notFoundResponse('Booking not found');
        }

        // Verify user is the athlete
        if ($booking->getAthlete()->getId() !== $user->getId()) {
            return $this->forbiddenResponse('Only the athlete can request downloads');
        }

        // Check if deliverables exist
        $deliverables = $booking->getDeliverables();
        if ($deliverables->count() === 0) {
            return $this->errorResponse(
                'No deliverables available for this booking',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Generate ZIP file
        $zipPath = $this->generateZipFile($booking);
        if (!$zipPath) {
            return $this->errorResponse(
                'Failed to generate ZIP file',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Upload ZIP to R2
        $zipKey = "deliverables/{$id}/archive.zip";
        $zipUrl = $this->r2->uploadFile($zipPath, $zipKey);

        // Generate signed URL (expires in 7 days)
        $signedUrl = $this->r2->generateSignedUrl($zipKey, '+7 days');

        // Mark download as requested
        $booking->setDeliverableDownloadRequestedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Send email with tracking pixel
        $trackingToken = bin2hex(random_bytes(32));
        $booking->setDeliverableTrackingToken($trackingToken);
        $this->em->flush();

        $this->emailService->sendDeliverableEmail(
            $user->getEmail(),
            $booking,
            $signedUrl,
            $trackingToken
        );

        // Clean up local ZIP
        @unlink($zipPath);

        return $this->createApiResponse([
            'message' => 'Download link sent to your email',
            'expiresIn' => '7 days',
            'filesCount' => $deliverables->count(),
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Track email open (called by tracking pixel)
     * GET /api/deliverables/track/{token}
     * Returns 1x1 transparent GIF
     */
    #[Route('/track/{token}', name: 'deliverable_track', methods: ['GET'])]
    public function trackEmailOpen(string $token): Response
    {
        // Find booking by tracking token
        $booking = $this->bookingRepository->findOneBy([
            'deliverableTrackingToken' => $token,
        ]);

        if ($booking && !$booking->getDeliverableDownloadedAt()) {
            // Mark as downloaded
            $booking->setDeliverableDownloadedAt(new \DateTimeImmutable());
            $this->em->flush();

            // Trigger Stripe payout
            try {
                $this->stripePayoutService->processBookingPayout($booking);
            } catch (\Exception $e) {
                error_log('Payout failed: ' . $e->getMessage());
            }
        }

        // Return 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return new Response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * ✅ Delete a deliverable (creator only)
     * DELETE /api/deliverables/{id}
     */
    #[Route('/{id}', name: 'deliverable_delete', methods: ['DELETE'])]
    public function delete(
        MediaAsset $asset,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Verify user is the owner
        if ($asset->getOwner()->getId() !== $user->getId()) {
            return $this->forbiddenResponse('You can only delete your own files');
        }

        // Verify it's a deliverable
        if ($asset->getPurpose() !== MediaAsset::PURPOSE_BOOKING_DELIVERABLE) {
            return $this->forbiddenResponse('This is not a deliverable');
        }

        // Delete from R2
        $this->r2->delete($asset->getStorageKey());

        // Delete from database
        $this->em->remove($asset);
        $this->em->flush();

        return $this->renderDeletedResponse('Deliverable deleted successfully');
    }

    /**
     * ✅ Generate ZIP file from booking deliverables
     */
    private function generateZipFile(Booking $booking): ?string
    {
        $deliverables = $booking->getDeliverables();
        if ($deliverables->count() === 0) {
            return null;
        }

        // Create temp directory
        $tempDir = sys_get_temp_dir() . '/deliverables_' . uniqid();
        @mkdir($tempDir, 0777, true);

        $zipPath = $tempDir . '/archive.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return null;
        }

        // Download each file and add to ZIP
        foreach ($deliverables as $asset) {
            $publicUrl = $asset->getPublicUrl();
            $filename = $asset->getFilename();

            // Download file
            $fileContent = @file_get_contents($publicUrl);
            if ($fileContent === false) {
                continue;
            }

            // Add to ZIP
            $zip->addFromString($filename, $fileContent);
        }

        $zip->close();

        return $zipPath;
    }
}
