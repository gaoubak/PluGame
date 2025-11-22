<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service to calculate and update creator ratings
 */
class RatingService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepo,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * Calculate and update the average rating for a creator
     *
     * @param User $creator The creator whose rating to update
     * @return array ['avgRating' => string, 'ratingsCount' => int]
     */
    public function updateCreatorRating(User $creator): array
    {
        $creatorProfile = $creator->getCreatorProfile();

        if (!$creatorProfile) {
            return ['avgRating' => null, 'ratingsCount' => 0];
        }

        // Get all reviews for this creator
        $reviews = $this->reviewRepo->findBy(['creator' => $creator]);

        if (empty($reviews)) {
            $creatorProfile->setAvgRating(null);
            $creatorProfile->setRatingsCount(0);
            $this->em->flush();

            return ['avgRating' => null, 'ratingsCount' => 0];
        }

        // Calculate average rating
        $totalRating = 0;
        $count = 0;

        foreach ($reviews as $review) {
            $totalRating += $review->getRating();
            $count++;
        }

        $avgRating = $totalRating / $count;
        $avgRatingFormatted = number_format($avgRating, 1); // e.g., "4.5"

        // Update creator profile
        $creatorProfile->setAvgRating($avgRatingFormatted);
        $creatorProfile->setRatingsCount($count);

        $this->em->flush();

        return [
            'avgRating' => $avgRatingFormatted,
            'ratingsCount' => $count,
            'rawAverage' => $avgRating
        ];
    }

    /**
     * Recalculate ratings for all creators
     * Useful for maintenance or after importing data
     *
     * @return array Statistics about the update
     */
    public function recalculateAllRatings(): array
    {
        $qb = $this->em->createQueryBuilder();
        $creators = $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.creatorProfile IS NOT NULL')
            ->getQuery()
            ->getResult();

        $stats = [
            'total_creators' => count($creators),
            'updated' => 0,
            'no_ratings' => 0,
        ];

        foreach ($creators as $creator) {
            $result = $this->updateCreatorRating($creator);

            if ($result['ratingsCount'] > 0) {
                $stats['updated']++;
            } else {
                $stats['no_ratings']++;
            }
        }

        return $stats;
    }

    /**
     * Get rating statistics for a creator
     * Includes breakdown by rating value (1-5 stars)
     *
     * @param User $creator
     * @return array
     */
    public function getRatingStatistics(User $creator): array
    {
        $reviews = $this->reviewRepo->findBy(['creator' => $creator]);

        $stats = [
            'totalReviews' => count($reviews),
            'avgRating' => null,
            'breakdown' => [
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0,
            ],
        ];

        if (empty($reviews)) {
            return $stats;
        }

        $totalRating = 0;
        foreach ($reviews as $review) {
            $rating = $review->getRating();
            $totalRating += $rating;
            $stats['breakdown'][$rating]++;
        }

        $stats['avgRating'] = round($totalRating / count($reviews), 1);

        // Calculate percentages
        foreach ($stats['breakdown'] as $stars => $count) {
            $stats['breakdown'][$stars] = [
                'count' => $count,
                'percentage' => count($reviews) > 0 ? round(($count / count($reviews)) * 100, 1) : 0
            ];
        }

        return $stats;
    }
}
