<?php

// src/Controller/DashboardController.php - CREATOR DASHBOARD API

namespace App\Controller;

use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * ✅ Get creator dashboard statistics
     * GET /api/dashboard/stats
     */
    #[Route('/stats', name: 'dashboard_stats', methods: ['GET'])]
    public function getStats(
        #[CurrentUser] User $user
    ): JsonResponse {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->errorResponse(
                'Creator profile required',
                Response::HTTP_FORBIDDEN
            );
        }

        // Get all bookings as creator
        $bookings = $this->bookingRepository->findBy(['creator' => $user]);

        // Calculate stats
        $pendingBookings = count(array_filter($bookings, fn($b) => $b->getStatus() === 'PENDING'));

        $upcomingBookings = count(array_filter($bookings, function ($b) {
            return $b->getStatus() === 'ACCEPTED' && $b->getStartTime() > new \DateTimeImmutable();
        }));

        // This month stats
        $thisMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $completedThisMonth = count(array_filter($bookings, function ($b) use ($thisMonth) {
            return $b->getStatus() === 'COMPLETED' &&
                   $b->getCreatedAt() >= $thisMonth;
        }));

        // Total revenue (from completed bookings with payout)
        $totalRevenueCents = 0;
        foreach ($bookings as $booking) {
            if ($booking->getPayoutCompletedAt() && $booking->getCreatorAmountCents()) {
                $totalRevenueCents += $booking->getCreatorAmountCents();
            }
        }

        // Rating stats
        $creatorProfile = $user->getCreatorProfile();
        $averageRating = $creatorProfile->getAvgRating() ?? 0;
        $totalReviews = $creatorProfile->getRatingsCount() ?? 0;

        return $this->createApiResponse([
            'totalRevenueCents' => $totalRevenueCents,
            'pendingBookings' => $pendingBookings,
            'upcomingBookings' => $upcomingBookings,
            'completedThisMonth' => $completedThisMonth,
            'averageRating' => (float) $averageRating,
            'totalReviews' => $totalReviews,
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Get creator analytics data
     * GET /api/dashboard/analytics
     */
    #[Route('/analytics', name: 'dashboard_analytics', methods: ['GET'])]
    public function getAnalytics(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->errorResponse(
                'Creator profile required',
                Response::HTTP_FORBIDDEN
            );
        }

        $period = $request->query->get('period', 'month'); // week, month, year

        // Get all bookings
        $bookings = $this->bookingRepository->findBy(['creator' => $user]);

        // Date ranges
        $now = new \DateTimeImmutable();
        $thisMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $lastMonth = new \DateTimeImmutable('first day of last month 00:00:00');
        $endLastMonth = new \DateTimeImmutable('last day of last month 23:59:59');

        // Filter bookings by status
        $completedBookings = array_filter($bookings, fn($b) => $b->getStatus() === 'COMPLETED');
        $cancelledBookings = array_filter($bookings, fn($b) => $b->getStatus() === 'CANCELLED');

        // Revenue calculations
        $thisMonthRevenue = 0;
        $lastMonthRevenue = 0;
        $totalRevenue = 0;

        foreach ($bookings as $booking) {
            if ($booking->getPayoutCompletedAt() && $booking->getCreatorAmountCents()) {
                $amount = $booking->getCreatorAmountCents();
                $totalRevenue += $amount;

                $payoutDate = $booking->getPayoutCompletedAt();
                if ($payoutDate >= $thisMonth) {
                    $thisMonthRevenue += $amount;
                } elseif ($payoutDate >= $lastMonth && $payoutDate <= $endLastMonth) {
                    $lastMonthRevenue += $amount;
                }
            }
        }

        // Calculate growth
        $revenueGrowth = 0;
        if ($lastMonthRevenue > 0) {
            $revenueGrowth = (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
        }

        // Average booking value
        $avgBookingValue = 0;
        if (count($completedBookings) > 0) {
            $totalBookingValue = array_reduce($completedBookings, fn($sum, $b) => $sum + $b->getTotalCents(), 0);
            $avgBookingValue = $totalBookingValue / count($completedBookings);
        }

        // Conversion rate
        $conversionRate = 0;
        if (count($bookings) > 0) {
            $conversionRate = (count($completedBookings) / count($bookings)) * 100;
        }

        // Top service
        $serviceRevenue = [];
        foreach ($completedBookings as $booking) {
            $service = $booking->getService();
            if ($service) {
                $serviceName = $service->getTitle();
                if (!isset($serviceRevenue[$serviceName])) {
                    $serviceRevenue[$serviceName] = [
                        'revenue' => 0,
                        'count' => 0,
                    ];
                }
                $serviceRevenue[$serviceName]['revenue'] += $booking->getCreatorAmountCents() ?? 0;
                $serviceRevenue[$serviceName]['count']++;
            }
        }

        $topService = null;
        $maxRevenue = 0;
        foreach ($serviceRevenue as $name => $data) {
            if ($data['revenue'] > $maxRevenue) {
                $maxRevenue = $data['revenue'];
                $topService = [
                    'name' => $name,
                    'revenue' => $data['revenue'],
                    'count' => $data['count'],
                ];
            }
        }

        return $this->createApiResponse([
            'totalRevenue' => $totalRevenue,
            'thisMonthRevenue' => $thisMonthRevenue,
            'lastMonthRevenue' => $lastMonthRevenue,
            'revenueGrowth' => round($revenueGrowth, 2),
            'totalBookings' => count($bookings),
            'completedBookings' => count($completedBookings),
            'cancelledBookings' => count($cancelledBookings),
            'conversionRate' => round($conversionRate, 2),
            'averageBookingValue' => (int) $avgBookingValue,
            'topService' => $topService,
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Get recent bookings for dashboard
     * GET /api/dashboard/recent-bookings
     */
    #[Route('/recent-bookings', name: 'dashboard_recent_bookings', methods: ['GET'])]
    public function getRecentBookings(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->errorResponse(
                'Creator profile required',
                Response::HTTP_FORBIDDEN
            );
        }

        $limit = min(10, max(1, (int) $request->query->get('limit', 5)));

        $bookings = $this->bookingRepository->findBy(
            ['creator' => $user],
            ['createdAt' => 'DESC'],
            $limit
        );

        $data = array_map(function ($booking) {
            $athlete = $booking->getAthlete();
            $service = $booking->getService();

            return [
                'id' => $booking->getId(),
                'status' => $booking->getStatus(),
                'startTime' => $booking->getStartTime()->format(\DATE_ATOM),
                'endTime' => $booking->getEndTime()->format(\DATE_ATOM),
                'priceCents' => $booking->getTotalCents(),
                'createdAt' => $booking->getCreatedAt()->format(\DATE_ATOM),
                'athlete' => [
                    'id' => $athlete->getId(),
                    'username' => $athlete->getUsername(),
                    'userPhoto' => $athlete->getUserPhoto(),
                ],
                'service' => [
                    'id' => $service?->getId(),
                    'title' => $service?->getTitle(),
                ],
            ];
        }, $bookings);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * ✅ Get recent revenues for dashboard
     * GET /api/dashboard/recent-revenues
     */
    #[Route('/recent-revenues', name: 'dashboard_recent_revenues', methods: ['GET'])]
    public function getRecentRevenues(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->errorResponse(
                'Creator profile required',
                Response::HTTP_FORBIDDEN
            );
        }

        $limit = min(10, max(1, (int) $request->query->get('limit', 5)));

        // Get bookings where remaining payment has been made (revenue earned)
        // Note: remainingPaidAt is set when athlete completes the 70% payment
        $bookings = $this->em->createQueryBuilder()
            ->select('b')
            ->from('App\Entity\Booking', 'b')
            ->where('b.creator = :creator')
            ->andWhere('b.remainingPaidAt IS NOT NULL')
            ->setParameter('creator', $user)
            ->orderBy('b.remainingPaidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function ($booking) {
            $athlete = $booking->getAthlete();
            $service = $booking->getService();

            // Calculate creator's revenue (total - platform fee)
            $totalCents = $booking->getTotalCents();
            $platformFeePercent = 15; // 15% platform fee
            $platformFeeCents = (int) ($totalCents * ($platformFeePercent / 100));
            $creatorAmountCents = $totalCents - $platformFeeCents;

            return [
                'id' => $booking->getId(),
                'amountCents' => $creatorAmountCents,
                'totalCents' => $totalCents,
                'platformFeeCents' => $platformFeeCents,
                'isPaidOut' => $booking->getPayoutCompletedAt() !== null,
                'createdAt' => $booking->getRemainingPaidAt()->format(\DATE_ATOM),
                'booking' => [
                    'id' => $booking->getId(),
                    'service' => $service?->getTitle(),
                    'athlete' => [
                        'id' => $athlete->getId(),
                        'username' => $athlete->getUsername(),
                    ],
                ],
            ];
        }, $bookings);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }
}
