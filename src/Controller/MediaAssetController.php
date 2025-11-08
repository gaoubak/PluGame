<?php

// src/Controller/MediaAssetController.php
namespace App\Controller;

use App\Entity\MediaAsset;
use App\Entity\User;
use App\Entity\Booking;
use App\Entity\CreatorProfile;
use App\Entity\AthleteProfile;
use App\Service\R2Storage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api/media')]
class MediaAssetController extends AbstractController
{
    public function __construct(
        private readonly R2Storage $r2,
        private readonly EntityManagerInterface $em,
        private readonly Security $security
    ) {
    }

    /**
     * Multipart upload to API, then create MediaAsset and link target.
     * form-data:
     *   - file: (required)
     *   - purpose: AVATAR | CREATOR_FEED | ATHLETE_FEED | BOOKING_DELIVERABLE
     *   - targetId: (optional) booking id, or profile id (if you want explicit)
     */
    #[Route('/upload', name: 'media_asset_upload', methods: ['POST'])]
    public function upload(Request $req): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $file    = $req->files->get('file');
        $purpose = (string)($req->request->get('purpose') ?? '');
        $targetId = $req->request->get('targetId'); // for booking or explicit profile link

        if (!$file || !$purpose) {
            return $this->json(['message' => 'file and purpose are required'], 400);
        }

        // Decide R2 prefix based on purpose
        $prefix = match ($purpose) {
            MediaAsset::PURPOSE_AVATAR             => 'avatars',
            MediaAsset::PURPOSE_BOOKING_DELIVERABLE=> 'deliverables',
            MediaAsset::PURPOSE_CREATOR_FEED       => 'creator',
            MediaAsset::PURPOSE_ATHLETE_FEED       => 'athlete',
            default                                => 'uploads',
        };

        // Upload to R2
        $put = $this->r2->upload($file, $prefix); // ['key','url']
        $contentType = $file->getClientMimeType() ?? 'application/octet-stream';
        $bytes       = $file->getSize() ?? 0;

        // Build asset
        $asset = (new MediaAsset())
            ->setOwner($user)
            ->setPurpose($purpose)
            ->setStorageKey($put['key'])
            ->setPublicUrl($put['url'] ?: null)
            ->setFilename($file->getClientOriginalName())
            ->setContentType($contentType)
            ->setBytes((int)$bytes);

        // Link to the right target
        switch ($purpose) {
            case MediaAsset::PURPOSE_AVATAR:
                // Link to user as avatar (and update profile picture)
                $user->setUserPhoto($put['url'] ?: $put['key']);
                break;

            case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                if (!$targetId) {
                    return $this->json(['message' => 'targetId (booking id) required'], 400);
                }
                $booking = $this->em->getRepository(Booking::class)->find($targetId);
                if (!$booking) {
                    return $this->json(['message' => 'Booking not found'], 404);
                }
                // Optionally enforce that current user is booking->creator
                if ($booking->getCreator()->getId() !== $user->getId()) {
                    return $this->json(['message' => 'Forbidden'], 403);
                }
                $asset->setBooking($booking);
                break;

            case MediaAsset::PURPOSE_CREATOR_FEED:
                // Link to your creatorProfile (implicit from user)
                $cp = $user->getCreatorProfile();
                if (!$cp) {
                    return $this->json(['message' => 'No creator profile'], 400);
                }
                $asset->setCreatorProfile($cp);
                break;

            case MediaAsset::PURPOSE_ATHLETE_FEED:
                $ap = $user->getAthleteProfile();
                if (!$ap) {
                    return $this->json(['message' => 'No athlete profile'], 400);
                }
                $asset->setAthleteProfile($ap);
                break;
        }

        $this->em->persist($asset);
        $this->em->flush();

        return $this->json([
            'id'        => (string)$asset->getId(),
            'purpose'   => $asset->getPurpose(),
            'key'       => $asset->getStorageKey(),
            'publicUrl' => $asset->getPublicUrl(),
            'owner'     => $user->getId(),
        ], 201);
    }

    /**
     * If you do client-direct uploads (presigned PUT), call this to REGISTER the asset.
     * JSON:
     *  {
     *    "purpose": "BOOKING_DELIVERABLE",
     *    "key": "deliverables/file-abc123.mp4",
     *    "filename": "file.mp4",
     *    "contentType": "video/mp4",
     *    "bytes": 123456789,
     *    "targetId": "booking-uuid-or-null"
     *  }
     */
    #[Route('/register', name: 'media_asset_register', methods: ['POST'])]
    public function register(Request $req): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $p = json_decode((string)$req->getContent(), true) ?? [];
        $purpose = (string)($p['purpose'] ?? '');
        $key     = (string)($p['key'] ?? '');
        $filename = (string)($p['filename'] ?? 'file');
        $contentType = (string)($p['contentType'] ?? 'application/octet-stream');
        $bytes = (int)($p['bytes'] ?? 0);
        $targetId = $p['targetId'] ?? null;

        if (!$purpose || !$key) {
            return $this->json(['message' => 'purpose and key are required'], 400);
        }

        $asset = (new MediaAsset())
            ->setOwner($user)
            ->setPurpose($purpose)
            ->setStorageKey($key)
            ->setFilename($filename)
            ->setContentType($contentType)
            ->setBytes($bytes)
            // try to compute public url if you have a publicBaseUrl configured
            ->setPublicUrl($this->r2->publicUrl($key) ?: null);

        switch ($purpose) {
            case MediaAsset::PURPOSE_AVATAR:
                $user->setUserPhoto($asset->getPublicUrl() ?: $asset->getStorageKey());
                break;

            case MediaAsset::PURPOSE_BOOKING_DELIVERABLE:
                if (!$targetId) {
                    return $this->json(['message' => 'targetId (booking id) required'], 400);
                }
                $booking = $this->em->getRepository(Booking::class)->find($targetId);
                if (!$booking) {
                    return $this->json(['message' => 'Booking not found'], 404);
                }
                if ($booking->getCreator()->getId() !== $user->getId()) {
                    return $this->json(['message' => 'Forbidden'], 403);
                }
                $asset->setBooking($booking);
                break;

            case MediaAsset::PURPOSE_CREATOR_FEED:
                $cp = $user->getCreatorProfile();
                if (!$cp) {
                    return $this->json(['message' => 'No creator profile'], 400);
                }
                $asset->setCreatorProfile($cp);
                break;

            case MediaAsset::PURPOSE_ATHLETE_FEED:
                $ap = $user->getAthleteProfile();
                if (!$ap) {
                    return $this->json(['message' => 'No athlete profile'], 400);
                }
                $asset->setAthleteProfile($ap);
                break;
        }

        $this->em->persist($asset);
        $this->em->flush();

        return $this->json([
            'id'        => (string)$asset->getId(),
            'key'       => $asset->getStorageKey(),
            'publicUrl' => $asset->getPublicUrl(),
        ], 201);
    }
}
