<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Optimized Booking Repository with eager loading to prevent N+1 queries
 *
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepositoryOptimized extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Find booking with all related data (NO N+1 queries)
     *
     * Loads in single query:
     * - Booking
     * - Athlete (User)
     * - Creator (User)
     * - Service
     * - Segments
     * - Payment
     * - Review
     */
    public function findByIdWithRelations(string $id): ?Booking
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.athlete', 'athlete')->addSelect('athlete')
            ->leftJoin('b.creator', 'creator')->addSelect('creator')
            ->leftJoin('b.service', 'service')->addSelect('service')
            ->leftJoin('b.segments', 'segments')->addSelect('segments')
            ->leftJoin('b.payment', 'payment')->addSelect('payment')
            ->leftJoin('b.review', 'review')->addSelect('review')
            ->leftJoin('b.conversation', 'conversation')->addSelect('conversation')
            ->where('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all bookings for a user (as athlete) with optimized loading
     *
     * @return Booking[]
     */
    public function findByAthleteOptimized(User $user, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.athlete', 'athlete')->addSelect('athlete')
            ->leftJoin('b.creator', 'creator')->addSelect('creator')
            ->leftJoin('b.service', 'service')->addSelect('service')
            ->leftJoin('b.payment', 'payment')->addSelect('payment')
            ->where('b.athlete = :user')
            ->setParameter('user', $user)
            ->orderBy('b.startTime', 'DESC');

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all bookings for a user (as creator) with optimized loading
     *
     * @return Booking[]
     */
    public function findByCreatorOptimized(User $user, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.athlete', 'athlete')->addSelect('athlete')
            ->leftJoin('b.creator', 'creator')->addSelect('creator')
            ->leftJoin('b.service', 'service')->addSelect('service')
            ->leftJoin('b.payment', 'payment')->addSelect('payment')
            ->where('b.creator = :user')
            ->setParameter('user', $user)
            ->orderBy('b.startTime', 'DESC');

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Paginated list with eager loading
     */
    public function findAllPaginated(int $page = 1, int $limit = 20, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.athlete', 'athlete')->addSelect('athlete')
            ->leftJoin('b.creator', 'creator')->addSelect('creator')
            ->leftJoin('b.service', 'service')->addSelect('service')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit);

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count total bookings (for pagination)
     */
    public function countAll(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)');

        if ($status) {
            $qb->where('b.status = :status')
               ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find upcoming bookings for a creator (for dashboard)
     */
    public function findUpcomingForCreator(User $creator, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.athlete', 'athlete')->addSelect('athlete')
            ->leftJoin('b.service', 'service')->addSelect('service')
            ->where('b.creator = :creator')
            ->andWhere('b.startTime > :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('creator', $creator)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('statuses', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->orderBy('b.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for a creator (single query)
     */
    public function getCreatorStats(User $creator): array
    {
        $result = $this->createQueryBuilder('b')
            ->select([
                'COUNT(b.id) as totalBookings',
                'SUM(CASE WHEN b.status = :completed THEN 1 ELSE 0 END) as completedBookings',
                'SUM(CASE WHEN b.status = :pending THEN 1 ELSE 0 END) as pendingBookings',
                'SUM(CASE WHEN b.status = :cancelled THEN 1 ELSE 0 END) as cancelledBookings',
                'SUM(CASE WHEN b.status = :completed THEN b.totalCents ELSE 0 END) as totalRevenueCents',
            ])
            ->where('b.creator = :creator')
            ->setParameter('creator', $creator)
            ->setParameter('completed', Booking::STATUS_COMPLETED)
            ->setParameter('pending', Booking::STATUS_PENDING)
            ->setParameter('cancelled', Booking::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalBookings' => (int) $result['totalBookings'],
            'completedBookings' => (int) $result['completedBookings'],
            'pendingBookings' => (int) $result['pendingBookings'],
            'cancelledBookings' => (int) $result['cancelledBookings'],
            'totalRevenueCents' => (int) $result['totalRevenueCents'],
        ];
    }
}
