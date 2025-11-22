<?php

namespace App\Service\Feed;

use App\Entity\MediaAsset;
use App\Entity\User;
use App\Entity\CreatorProfile;
use App\Entity\ServiceOffering; // <-- ADDED: Necessary for type hinting in getFeaturedService
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class CreatorFeedService
{
    private const MAX_RESULTS = 500;
    private const MAX_MEDIA_PER_CREATOR = 4;
    private const CACHE_TTL = 300; // 5 minutes
    private const HALF_LIFE_HOURS = 48;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getCreatorFeed(User $viewer, array $filters): array
    {
        // Validate and sanitize inputs
        $filters = $this->sanitizeFilters($filters);

        // Cache key based on filters
        $cacheKey = sprintf(
            'creator_feed_%s_%s',
            $viewer->getId(),
            md5(json_encode($filters))
        );

        try {
            // Temporarily bypass cache for debugging
            $result = $this->buildFeed($viewer, $filters);
            $this->logger->info('Feed built successfully', [
                'viewer_id' => $viewer->getId(),
                'total' => $result['total'] ?? 0
            ]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate creator feed', [
                'viewer_id' => $viewer->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->emptyFeed($filters['page'], $filters['limit']);
        }
    }

    private function buildFeed(User $viewer, array $filters): array
    {
        $now = new \DateTimeImmutable();
        $maxMediaPerCreator = $filters['maxMedia'] ?? self::MAX_MEDIA_PER_CREATOR;
        // Query data
        $media = $this->queryMedia($filters);
        $this->logger->info('Media queried', ['count' => count($media)]);

        $profiles = $this->queryProfiles($filters);
        $this->logger->info('Profiles queried', ['count' => count($profiles)]);

        $userIds = array_map(fn($cp) => $cp->getUser()->getId(), $profiles);
        $likesAndCommentsCounts = $this->getLikesAndCommentsCountsByUserIds($userIds);
        // Get user's blocked creators
        $blockedCreatorIds = $this->getBlockedCreatorIds($viewer);
        // Group medias per creator
        $mediasByCreator = $this->groupMediaByCreator($media, $maxMediaPerCreator);
        // Get user's booking history for loyalty boost
        $bookedCreatorIds = $this->getBookedCreatorIds($viewer);
        $featured = $this->getFeaturedService($userIds); 
        // Build and score cards
        $cards = [];
        foreach ($profiles as $cp) {
            $u = $cp->getUser();
            $uid = $u->getId();

            // Skip blocked creators
            if (in_array($uid, $blockedCreatorIds)) {
                continue;
            }

            // Calculate score
            $lastActive = $cp->getUser()->getUpdatedAt() ?? $cp->getCreatedAt();
            $recency = $this->halfLifeScore($lastActive, $now, self::HALF_LIFE_HOURS);
            $rating  = $this->normalizeRating($cp->getAvgRating());
            $hasMedia = !empty($mediasByCreator[$uid]) ? 0.15 : 0.0;
            $intrBoost = $this->interestBoost(
                $filters['interests'] ?? [],
                $cp->getSpecialties() ?? []
            );
            $loyaltyBoost = in_array($uid, $bookedCreatorIds) ? 0.10 : 0.0;

            $score = 0.45 * $recency
                   + 0.30 * $rating
                   + $hasMedia
                   + $intrBoost
                   + $loyaltyBoost
                   + $this->jitter();

            $likesCount = $likesAndCommentsCounts[$uid]['likes'] ?? 0;
            $commentsCount = $likesAndCommentsCounts[$uid]['comments'] ?? 0;
            $service = $featured[$uid] ?? null;

            $cards[] = [
                'score' => $score,
                'creatorProfile' => [
                    'displayName' => $cp->getDisplayName(),
                    'city'        => $cp->getBaseCity(),
                    'bio'         => $cp->getBio(),
                    'rating'      => $cp->getAvgRating(),
                    'ratingsCount' => $cp->getRatingsCount(),
                    'specialties' => $cp->getSpecialties() ?? [],
                    'medias'      => $mediasByCreator[$uid] ?? [],
                    'likesCount'  => $likesCount,
                    'commentsCount' => $commentsCount,
                    'user'        => [
                        'id' => $u->getId(),
                        'username' => $u->getUsername(),
                        'fullName' => $u->getFullName(),
                        'userPhoto' => $u->getUserPhoto(),
                        'isVerified' => $u->isVerified(),
                    ],
                    'listing' => $service ? [
                        'id'       => (string)$service->getId(),
                        'title'    => $service->getTitle(),
                        'price'    => $service->getPriceCents(),
                        'currency' => $service->getCurrency(),
                        'includes' => $service->getIncludes(),
                        'kind'     => $service->getKind(),
                    ] : null,
                ]
            ];
        }

        // Sort and paginate
        usort($cards, fn($a, $b) => $b['score'] <=> $a['score']);

        $page = $filters['page'];
        $limit = $filters['limit'];
        $offset = ($page - 1) * $limit;
        $total = count($cards);

        $slice = array_slice($cards, $offset, $limit);
        $results = array_map(fn($x) => ['creatorProfile' => $x['creatorProfile']], $slice);

        return [
            'page'     => $page,
            'limit'    => $limit,
            'total'    => $total,
            'nextPage' => ($offset + $limit < $total) ? $page + 1 : null,
            'results'  => $results,
        ];
    }

    private function sanitizeFilters(array $filters): array
    {
        return [
            'city'       => trim((string)($filters['city'] ?? '')),
            'specialties' => $this->csvToArray($filters['specialties'] ?? ''),
            'gear'       => $this->csvToArray($filters['gear'] ?? ''),
            'minTravelRadiusKm' => max(0, (int)($filters['minTravelRadiusKm'] ?? 0)),
            'interests'  => $this->csvToArray($filters['interests'] ?? ''),
            'page'       => max(1, (int)($filters['page'] ?? 1)),
            'limit'      => max(1, min(50, (int)($filters['limit'] ?? 20))),
            'maxMedia'   => max(1, min(10, (int)($filters['maxMedia'] ?? 4))),
        ];
    }

    private function queryMedia(array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('m', 'u', 'cp')
            ->from(MediaAsset::class, 'm')
            ->join('m.owner', 'u')
            ->leftJoin('u.creatorProfile', 'cp')
            ->where('m.purpose = :p')
            ->setParameter('p', MediaAsset::PURPOSE_CREATOR_FEED)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(self::MAX_RESULTS);

        $this->applyFilters($qb, $filters, 'cp');

        return $qb->getQuery()->getResult();
    }

    private function queryProfiles(array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('cp', 'u')
            ->from(CreatorProfile::class, 'cp')
            ->join('cp.user', 'u')
            ->setMaxResults(self::MAX_RESULTS);

        $this->applyFilters($qb, $filters, 'cp');

        return $qb->getQuery()->getResult();
    }


    private function applyFilters($qb, array $filters, string $alias): void
    {
        if ($filters['city'] !== '') {
            $qb->andWhere("LOWER($alias.baseCity) = :city")
               ->setParameter('city', mb_strtolower($filters['city']));
        }

        if (!empty($filters['specialties'])) {
            $this->addJsonFilters($qb, "$alias.specialties", $filters['specialties'], 'spec');
        }

        if (!empty($filters['gear'])) {
            $this->addJsonFilters($qb, "$alias.gear", $filters['gear'], 'gear');
        }

        if ($filters['minTravelRadiusKm'] > 0) {
            $qb->andWhere("$alias.travelRadiusKm >= :rad")
               ->setParameter('rad', $filters['minTravelRadiusKm']);
        }
    }

    private function groupMediaByCreator(array $media, int $maxPerCreator): array
    {
        $grouped = [];
        foreach ($media as $m) {
            $owner = $m->getOwner();
            if (!$owner || !$owner->getCreatorProfile()) {
                continue;
            }

            $uid = $owner->getId();
            $grouped[$uid] ??= [];

            if (count($grouped[$uid]) < $maxPerCreator) {
                $grouped[$uid][] = [
                    'id'           => (string)$m->getId(),
                    'type'         => strtolower($m->getType()),
                    'url'          => $m->getPublicUrl() ?: '',
                    'thumbnailUrl' => $m->getThumbnailUrl(),
                    'caption'      => $m->getCaption(),
                    'aspectRatio'  => $m->getAspectRatio(),
                    'width'        => $m->getWidth(),
                    'height'       => $m->getHeight(),
                    'createdAt'    => $m->getCreatedAt()?->format(\DATE_ATOM),
                ];
            }
        }
        return $grouped;
    }

    private function getBlockedCreatorIds(User $viewer): array
    {
        // TODO: Implémenter système de blocage
        // Pour l'instant, retourne array vide
        return [];
    }

    private function getBookedCreatorIds(User $viewer): array
    {
        $bookings = $viewer->getBookingsAsAthlete();
        $creatorIds = [];

        foreach ($bookings as $booking) {
            $creator = $booking->getCreator();
            if ($creator && $booking->getStatus() === 'COMPLETED') {
                $creatorIds[] = $creator->getId();
            }
        }

        return array_unique($creatorIds);
    }

    private function addJsonFilters($qb, string $field, array $values, string $prefix): void
    {
        if (empty($values)) {
            return;
        }

        $i = 0;
        $or = $qb->expr()->orX();

        foreach ($values as $v) {
            $param = $prefix . $i++;
            // Using LIKE for MySQL compatibility
            // For PostgreSQL, use: @> operator
            $or->add("$field LIKE :$param");
            $qb->setParameter($param, '%' . json_encode($v, JSON_UNESCAPED_UNICODE) . '%');
        }

        $qb->andWhere($or);
    }

    private function csvToArray(?string $s): array
    {
        if (!$s) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $s))));
    }

    private function halfLifeScore(\DateTimeImmutable $t, \DateTimeImmutable $now, int $halfLifeHours): float
    {
        $diffHours = max(0, ($now->getTimestamp() - $t->getTimestamp()) / 3600);
        return pow(0.5, $diffHours / max(1, $halfLifeHours));
    }

    private function normalizeRating(?string $avg): float
    {
        if ($avg === null) {
            return 0.4;
        }
        $v = (float)$avg;
        return max(0.0, min(1.0, $v / 5.0));
    }

    private function interestBoost(array $interests, array $specialties): float
    {
        if (!$interests || !$specialties) {
            return 0.0;
        }

        $a = array_map('mb_strtolower', $interests);
        $b = array_map('mb_strtolower', $specialties);
        $overlap = count(array_intersect($a, $b));

        return min(0.10, 0.03 + 0.02 * $overlap);
    }

    private function jitter(): float
    {
        return mt_rand(0, 6) / 1000.0;
    }

    private function emptyFeed(int $page, int $limit): array
    {
        return [
            'page'     => $page,
            'limit'    => $limit,
            'total'    => 0,
            'nextPage' => null,
            'results'  => [],
        ];
    }

    private function getLikesAndCommentsCountsByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        // Initialize counts for all creators to 0
        $counts = [];
        foreach ($userIds as $userId) {
            $counts[$userId] = [
                'likes' => 0,
                'comments' => 0,
            ];
        }

        try {
            // Requête pour les likes (Counting likes authored by the user)
            $likesQuery = $this->em->createQuery('
                SELECT IDENTITY(l.user) as user_id, COUNT(l.id) as likes_count
                FROM App\Entity\Like l
                WHERE l.user IN (:userIds)
                GROUP BY l.user
            ')->setParameter('userIds', $userIds);
            
            $likesResults = $likesQuery->getResult();
            
            // Requête pour les commentaires (Counting comments received by the post/profile owner)
            $commentsQuery = $this->em->createQuery('
                SELECT IDENTITY(c.post) as post_id, COUNT(c.id) as comments_count
                FROM App\Entity\Comment c
                WHERE c.post IN (:postUserIds)
                GROUP BY c.post
            ')
            ->setParameter('postUserIds', $userIds);
           
            $commentsResults = $commentsQuery->getResult();

            // Populate counts
            foreach ($likesResults as $row) {
                $uid = $row['user_id'];
                // Defensive check to ensure the ID is one we are tracking
                if (array_key_exists($uid, $counts)) {
                    $counts[$uid]['likes'] = (int) $row['likes_count'];
                } else {
                    $this->logger->warning('Like count result contained unexpected user ID', ['user_id' => $uid]);
                }
            }
            
            foreach ($commentsResults as $row) {
                $uid = $row['post_id'];
                // Defensive check to ensure the ID is one we are tracking
                if (array_key_exists($uid, $counts)) {
                    $counts[$uid]['comments'] = (int) $row['comments_count'];
                } else {
                    $this->logger->warning('Comment count result contained unexpected post ID', ['post_id' => $uid]);
                }
            }
            
        } catch (\Exception $e) {
            // FIX: Log the error and return the initialized array (all zeros) instead of crashing.
            // This prevents the entire buildFeed from failing and returning an empty feed.
            $this->logger->error('Failed to retrieve likes/comments counts', [
                'error' => $e->getMessage(),
                'user_ids' => $userIds,
            ]);
            // Return the initial array of zeros, preserving the feed structure.
            return $counts;
        }
        
        return $counts;
    }

    private function getFeaturedService(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('s', 'u')
            ->from('App\Entity\ServiceOffering', 's')
            ->join('s.creator', 'u')
            ->where('u.id IN (:ids)')
            ->andWhere('s.featured = true')
            ->setParameter('ids', $userIds);

        /** @var ServiceOffering[] $results */
        $results = $qb->getQuery()->getResult();

        $featuredMap = [];

        foreach ($results as $service) {
            $creatorId = $service->getCreator()->getId();
            $featuredMap[$creatorId] = $service;
        }

        return $featuredMap;
    }

}