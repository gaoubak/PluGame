<?php

namespace App\Service\Feed;

use App\Entity\MediaAsset;
use App\Entity\User;
use App\Entity\CreatorProfile;
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
            return $this->cache->get($cacheKey, function () use ($viewer, $filters) {
                return $this->buildFeed($viewer, $filters);
            }, self::CACHE_TTL);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate creator feed', [
                'viewer_id' => $viewer->getId(),
                'error' => $e->getMessage()
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
        //dump("media :",$media);
        $profiles = $this->queryProfiles($filters);
        // Get user's blocked creators
        $blockedCreatorIds = $this->getBlockedCreatorIds($viewer);
        // Group medias per creator
        $mediasByCreator = $this->groupMediaByCreator($media, $maxMediaPerCreator);
        // Get user's booking history for loyalty boost
        $bookedCreatorIds = $this->getBookedCreatorIds($viewer);

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
                    'user'        => [
                        'id' => $u->getId(),
                        'username' => $u->getUsername(),
                        'userPhoto' => $u->getUserPhoto(),
                    ]
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
        $profiles = $this->em->getRepository(CreatorProfile::class)->findAll();
        //$profiles = $this->applyFilters($profiles, $filters, 'cp') ?? [];

        return (array) $profiles;
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
}
