<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\BookingSegment;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\AvailabilitySlotRepository;
use App\Repository\BookingRepository;
use App\Repository\ServiceOfferingRepository;
use App\Service\PricingService;
use App\Service\MercurePublisher;
use App\Traits\ApiResponseTrait;
use App\Traits\FormHandlerTrait;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\UserRepository;
use App\Service\Stripe\StripeService;
use OpenApi\Attributes as OA;

#[Route('/api/bookings')]
#[OA\Tag(name: 'Bookings')]
class BookingController extends AbstractFOSRestController
{
    use ApiResponseTrait;
    use FormHandlerTrait;

    public function __construct(
        private readonly BookingRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly Security $security,
        private readonly AvailabilitySlotRepository $slots,
        private readonly ServiceOfferingRepository $services,
        private readonly PricingService $pricing,
        private readonly UserRepository $userRepository,
        private readonly MercurePublisher $mercurePublisher,
        private readonly StripeService $stripeService,
    ) {
    }

    #[Route('/', name: 'booking_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings',
        summary: 'List all bookings',
        security: [['bearerAuth' => []]],
        tags: ['Bookings']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Page number',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Items per page',
        schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)
    )]
    #[OA\Response(
        response: 200,
        description: 'List of bookings',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Booking')),
                new OA\Property(
                    property: 'pagination',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'limit', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'totalPages', type: 'integer'),
                        new OA\Property(property: 'hasNext', type: 'boolean'),
                        new OA\Property(property: 'hasPrev', type: 'boolean'),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function list(): Response
    {
        $items = $this->repo->findAll();
        $data  = $this->serializer->normalize($items, null, ['groups' => ['booking:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'booking_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings/{id}',
        summary: 'Get booking details',
        security: [['bearerAuth' => []]],
        tags: ['Bookings']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Booking UUID',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Booking details',
        content: new OA\JsonContent(ref: '#/components/schemas/Booking')
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(
        response: 403,
        description: 'Forbidden - Not authorized to view this booking',
        content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
    )]
    #[OA\Response(response: 404, description: 'Booking not found')]
    public function getOne(Booking $booking): Response
    {
        // 🔒 Security: Prevent IDOR - verify user can view this booking
       // $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::VIEW, $booking);

        $data = $this->serializer->normalize($booking, null, ['groups' => ['booking:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * List current user's bookings where they are the ATHLETE.
     */
    #[Route('/mine/as-athlete', name: 'booking_my_as_athlete', methods: ['GET'])]
    public function myAsAthlete(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // ✨ RFC 7807: Use unauthorized exception
            throw ApiProblemException::unauthorized('You must be authenticated to access your bookings');
        }

        // Pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        // Get total count
        $total = $this->repo->count(['athlete' => $user]);

        // Get paginated results
        $items = $this->repo->findBy(
            ['athlete' => $user],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        $data  = $this->serializer->normalize($items, null, ['groups' => ['booking:read']]);

        return $this->createApiResponse([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * List current user's bookings where they are the CREATOR.
     */
    #[Route('/mine/as-creator', name: 'booking_my_as_creator', methods: ['GET'])]
    public function myAsCreator(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // ✨ RFC 7807: Use unauthorized exception
            throw ApiProblemException::unauthorized('You must be authenticated to access your bookings');
        }

        // Pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        // Get total count
        $total = $this->repo->count(['creator' => $user]);

        // Get paginated results
        $items = $this->repo->findBy(
            ['creator' => $user],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        $data  = $this->serializer->normalize($items, null, ['groups' => ['booking:read']]);

        return $this->createApiResponse([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/user/{userId}/as-athlete', name: 'booking_by_user_as_athlete', methods: ['GET'])]
    public function getByUserAsAthlete(string $userId, UserRepository $userRepo): Response
    {
        $user = $userRepo->find($userId);
        if (!$user) {
            // ✨ RFC 7807: Use notFound exception
            throw ApiProblemException::notFound('user', $userId);
        }

        $items = $this->repo->findBy(['athlete' => $user]);
        $data  = $this->serializer->normalize($items, null, ['groups' => ['booking:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/user/{userId}/as-creator', name: 'booking_by_user_as_creator', methods: ['GET'])]
    public function getByUserAsCreator(string $userId, UserRepository $userRepo): Response
    {
        $user = $userRepo->find($userId);
        if (!$user) {
            return $this->createApiResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $items = $this->repo->findBy(['creator' => $user]);
        $data  = $this->serializer->normalize($items, null, ['groups' => ['booking:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }


    /**
     * Create a booking:
     *  - MODE A: { "slotIds": ["uuid", ...], "serviceId": "uuid", "taxPercent": 20, ... }
     *  - MODE B: { "creatorId": "uuid", "serviceId": "uuid", "startTime": "...", "durationMin": 120, "taxPercent": 20, ... }
     *
     * Accepts: location (short), locationText, notes
     */
    #[Route('/create', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        /** @var User|null $athlete */
        $athlete = $this->security->getUser();
        if (!$athlete instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $p = json_decode($request->getContent(), true) ?? [];

        // Either slotIds[] OR (startTime + durationMin + creatorId)
        $slotIds    = $p['slotIds'] ?? null;
        $serviceId  = $p['serviceId'] ?? null;
        $creatorId  = $p['creatorId'] ?? null;
        $startIso   = $p['startTime'] ?? null;
        $minutesIn  = $p['durationMin'] ?? ($p['minutes'] ?? null);
        $location   = $p['location'] ?? null;       // short location
        $locationText = $p['locationText'] ?? null; // full address
        $notes      = $p['notes'] ?? null;
        $taxPercent = (int) ($p['taxPercent'] ?? 0);

        if (!$serviceId) {
            return $this->createApiResponse(['message' => 'serviceId required'], Response::HTTP_BAD_REQUEST);
        }
        $service = $this->services->find($serviceId);
        if (!$service) {
            return $this->createApiResponse(['message' => 'ServiceOffering not found'], Response::HTTP_NOT_FOUND);
        }

        $segments = [];
        $creator  = null;

        // MODE A: by slots
        if (is_array($slotIds) && !empty($slotIds)) {
            foreach ($slotIds as $sid) {
                $slot = $this->slots->find($sid);
                if (!$slot) {
                    return $this->createApiResponse(['message' => "Slot $sid not found"], Response::HTTP_NOT_FOUND);
                }
                if ($slot->isBooked()) {
                    return $this->createApiResponse(['message' => "Slot $sid already booked"], Response::HTTP_CONFLICT);
                }
                if ($creator === null) {
                    $creator = $slot->getCreator();
                } elseif ($slot->getCreator()->getId() !== $creator->getId()) {
                    return $this->createApiResponse(['message' => 'All slots must belong to the same creator'], Response::HTTP_BAD_REQUEST);
                }

                $segments[] = new BookingSegment(
                    $slot->getStartTime(),
                    $slot->getEndTime(),
                    0,            // price per segment computed later
                    $slot         // keep link to slot
                );
            }

        // MODE B: ad-hoc start + duration
        } else {
            if (!$startIso || !$minutesIn || !$creatorId) {
                return $this->createApiResponse(
                    ['message' => 'Provide either slotIds[] OR (startTime + durationMin + creatorId)'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            /** @var User|null $creator */
            $creator = $this->em->getRepository(User::class)->find($creatorId);
            if (!$creator) {
                return $this->createApiResponse(['message' => 'Creator not found'], Response::HTTP_NOT_FOUND);
            }

            try {
                $start = new \DateTimeImmutable($startIso);
            } catch (\Throwable $e) {
                return $this->createApiResponse(['message' => 'Invalid startTime format'], Response::HTTP_BAD_REQUEST);
            }

            $mins  = (int) $minutesIn;
            if ($mins <= 0) {
                return $this->createApiResponse(['message' => 'durationMin must be > 0'], Response::HTTP_BAD_REQUEST);
            }
            $end = $start->modify("+{$mins} minutes");

            $segments[] = new BookingSegment($start, $end, 0, null);
        }

        // Build booking
        $booking = (new Booking())
            ->setAthlete($athlete)
            ->setCreator($creator)
            ->setService($service)
            ->setLocation($location)
            ->setLocationText($locationText)
            ->setNotes($notes);

        foreach ($segments as $seg) {
            $seg->setBooking($booking);
            $booking->addSegment($seg);
            if ($seg->getSlot()) {
                $seg->getSlot()->setIsBooked(true);
            }
            $this->em->persist($seg);
        }

        // pricing
        $bookedMinutes = $booking->getBookedMinutes();
        $isPlugPlus    = method_exists($athlete, 'isPlugPlus') ? (bool) $athlete->isPlugPlus() : false;

        $quote = $this->pricing->quote($service, $bookedMinutes, $isPlugPlus, $taxPercent);
        $booking
            ->setSubtotalCents($quote['subtotal'])
            ->setFeeCents($quote['fee'])
            ->setTaxCents($quote['tax'])
            ->setTotalCents($quote['total']);

        $this->em->persist($booking);
        $this->em->flush();

        // Publish Mercure notification
        try {
            $this->mercurePublisher->publishBookingCreated($booking);
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        $data = [
            'id'        => (string) $booking->getId(),
            'startTime' => $booking->getStartTime()->format(\DATE_ATOM),
            'endTime'   => $booking->getEndTime()->format(\DATE_ATOM),
            'subtotal'  => $booking->getSubtotalCents(),
            'fee'       => $booking->getFeeCents(),
            'tax'       => $booking->getTaxCents(),
            'total'     => $booking->getTotalCents(),
            'status'    => $booking->getStatus(),
        ];
        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    #[Route('/{id}/accept', name: 'booking_accept', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bookings/{id}/accept',
        summary: 'Accept a booking (Creator only)',
        security: [['bearerAuth' => []]],
        tags: ['Bookings']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Booking UUID',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Booking accepted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ACCEPTED'),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(
        response: 403,
        description: 'Forbidden - Only creator can accept bookings',
        content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails')
    )]
    #[OA\Response(
        response: 409,
        description: 'Conflict - Booking not in PENDING status',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'type', type: 'string', example: 'booking-invalid-status'),
                new OA\Property(property: 'title', type: 'string', example: 'Booking Cannot Be Accepted'),
                new OA\Property(property: 'status', type: 'integer', example: 409),
                new OA\Property(property: 'detail', type: 'string'),
                new OA\Property(property: 'current_status', type: 'string'),
                new OA\Property(property: 'allowed_statuses', type: 'array', items: new OA\Items(type: 'string')),
            ]
        )
    )]
    public function accept(Booking $booking): Response
    {
        // 🔒 Security: Only creator can accept, verified by voter
        $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::ACCEPT, $booking);

        // ✨ RFC 7807: Throw exception instead of returning error response
        if ($booking->getStatus() !== Booking::STATUS_PENDING) {
            throw new ApiProblemException(
                status: 409,
                title: 'Booking Cannot Be Accepted',
                detail: "Cannot accept booking in '{$booking->getStatus()}' status. Only PENDING bookings can be accepted.",
                type: 'booking-invalid-status',
                additionalData: [
                    'booking_id' => $booking->getId(),
                    'current_status' => $booking->getStatus(),
                    'allowed_statuses' => [Booking::STATUS_PENDING]
                ]
            );
        }

        $booking->setStatus(Booking::STATUS_ACCEPTED);
        $this->em->flush();

        // Publish Mercure notification
        try {
            $this->mercurePublisher->publishBookingStatusChanged($booking);
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        return $this->createApiResponse(['status' => $booking->getStatus()], Response::HTTP_OK);
    }

    #[Route('/{id}/decline', name: 'booking_decline', methods: ['POST'])]
    public function decline(Booking $booking): Response
    {
        // 🔒 Security: Only creator can decline, verified by voter
        $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::DECLINE, $booking);

        // ✨ RFC 7807: Use conflict exception
        if ($booking->getStatus() !== Booking::STATUS_PENDING) {
            throw ApiProblemException::conflict(
                "Cannot decline booking in '{$booking->getStatus()}' status. Only PENDING bookings can be declined."
            );
        }

        $booking->setStatus(Booking::STATUS_DECLINED);

        // Optionally free any linked slots
        foreach ($booking->getSegments() as $seg) {
            if ($seg->getSlot()) {
                $seg->getSlot()->setIsBooked(false);
            }
        }

        $this->em->flush();

        // Publish Mercure notification
        try {
            $this->mercurePublisher->publishBookingStatusChanged($booking);
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        return $this->createApiResponse(['status' => $booking->getStatus()], Response::HTTP_OK);
    }

    /**
     * Cancel a booking (by athlete OR creator).
     * Body: { "reason": "some reason" }
     *
     * ✨ When cancelled, the athlete is automatically refunded via Stripe
     */
    #[Route('/{id}/cancel', name: 'booking_cancel', methods: ['POST'])]
    public function cancel(Booking $booking, Request $request): Response
    {
        // 🔒 Security: Verified by voter (athlete OR creator can cancel)
        $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::CANCEL, $booking);

        // ✨ RFC 7807: Clear error message about cancellation rules
        if (!$booking->canBeCancelled()) {
            throw ApiProblemException::conflict(
                "Booking in '{$booking->getStatus()}' status cannot be cancelled. " .
                "Only PENDING, ACCEPTED, or IN_PROGRESS bookings can be cancelled."
            );
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = $payload['reason'] ?? null;

        $user = $this->security->getUser();
        $booking->setStatus(Booking::STATUS_CANCELLED);
        $booking->setCancelledBy($user);
        $booking->setCancelReason($reason);

        // free slots
        foreach ($booking->getSegments() as $seg) {
            if ($seg->getSlot()) {
                $seg->getSlot()->setIsBooked(false);
            }
        }

        // 💰 Process refund to athlete if payment was made
        $refundProcessed = false;
        $refundError = null;

        if ($booking->getStripePaymentIntentId()) {
            try {
                // Get the payment entity (if exists)
                $payment = $booking->getPayment();

                if ($payment && $payment->getStatus() === 'completed') {
                    // Refund using Payment entity
                    $this->stripeService->refund($payment);
                    $refundProcessed = true;
                } elseif ($booking->getRemainingPaidAt()) {
                    // New flow: booking has payment but no Payment entity
                    // Refund the full amount
                    $this->stripeService->refundByPaymentIntentId(
                        $booking->getStripePaymentIntentId()
                    );
                    $refundProcessed = true;
                }

                if ($refundProcessed) {
                    error_log("Refund processed for booking {$booking->getId()}");
                }
            } catch (\Exception $e) {
                // Log error but don't fail the cancellation
                $refundError = $e->getMessage();
                error_log("Refund failed for booking {$booking->getId()}: {$refundError}");
            }
        }

        $this->em->flush();

        // Publish Mercure notification
        try {
            $this->mercurePublisher->publishBookingStatusChanged($booking);
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        $response = [
            'status' => $booking->getStatus(),
            'refundProcessed' => $refundProcessed,
        ];

        if ($refundError) {
            $response['refundError'] = $refundError;
            $response['message'] = 'Booking cancelled, but refund failed. Please contact support.';
        }

        return $this->createApiResponse($response, Response::HTTP_OK);
    }

    /**
     * Mark booking as completed (creator only).
     * Optionally body: { "completedAt": "2025-10-29T..." } else server time used.
     */
    #[Route('/{id}/complete', name: 'booking_complete', methods: ['POST'])]
    public function complete(Booking $booking, Request $request): Response
    {
        // 🔒 Security: Only creator can mark as complete, verified by voter
        $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::COMPLETE, $booking);

        // ✨ RFC 7807: Status validation
        if ($booking->getStatus() !== Booking::STATUS_ACCEPTED && $booking->getStatus() !== Booking::STATUS_IN_PROGRESS) {
            throw new ApiProblemException(
                status: 409,
                title: 'Booking Cannot Be Completed',
                detail: "Cannot complete booking in '{$booking->getStatus()}' status.",
                type: 'booking-invalid-status',
                additionalData: [
                    'current_status' => $booking->getStatus(),
                    'allowed_statuses' => [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS]
                ]
            );
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $completedAtIso = $payload['completedAt'] ?? null;
        $completedAt = null;
        if ($completedAtIso) {
            try {
                $completedAt = new \DateTimeImmutable($completedAtIso);
            } catch (\Throwable $e) {
                // ✨ RFC 7807: Date validation error
                throw ApiProblemException::badRequest(
                    'Invalid completedAt format. Expected ISO 8601 date string (e.g., "2025-11-07T10:30:00Z")'
                );
            }
        } else {
            $completedAt = new \DateTimeImmutable();
        }

        $booking->setStatus(Booking::STATUS_COMPLETED);
        $booking->setCompletedAt($completedAt);

        $this->em->flush();

        // Publish Mercure notification
        try {
            $this->mercurePublisher->publishBookingStatusChanged($booking);
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        return $this->createApiResponse([
            'status' => $booking->getStatus(),
            'completedAt' => $booking->getCompletedAt()?->format(\DATE_ATOM),
        ], Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'booking_delete', methods: ['DELETE'])]
    public function delete(Booking $booking): Response
    {
        // 🔒 Security: Only athlete can delete their booking, verified by voter
        $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::DELETE, $booking);

        // 🗑️ Soft Delete: Mark as deleted instead of removing from database
        $user = $this->security->getUser();
        $booking->softDelete($user instanceof \App\Entity\User ? $user : null);

        $this->em->flush();
        return $this->renderDeletedResponse('Booking deleted successfully');
    }

    /**
     * Restore a soft-deleted booking (admin/support only)
     */
    #[Route('/{id}/restore', name: 'booking_restore', methods: ['POST'])]
    public function restore(Booking $booking): Response
    {
        // 🔒 Security: Check permissions (you may want to add ROLE_ADMIN check here)
        $this->denyAccessUnlessGranted(\App\Security\Voter\BookingVoter::DELETE, $booking);

        if (!$booking->isDeleted()) {
            throw ApiProblemException::badRequest('Booking is not deleted');
        }

        // ♻️ Restore: Undo soft delete
        $booking->restore();
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Booking restored successfully',
            'booking_id' => $booking->getId(),
            'status' => $booking->getStatus()
        ], Response::HTTP_OK);
    }
}
