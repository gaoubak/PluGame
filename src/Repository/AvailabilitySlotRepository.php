<?php

// src/Repository/AvailabilitySlotRepository.php - AJOUTER CES MÉTHODES

namespace App\Repository;

use App\Entity\AvailabilitySlot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AvailabilitySlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilitySlot::class);
    }

    /**
     * Check if a time range overlaps with any existing slot for this creator
     *
     * @param User $creator
     * @param \DateTimeImmutable $start
     * @param \DateTimeImmutable $end
     * @param string|null $excludeId UUID to exclude from check (for updates)
     * @return bool True if overlap exists
     */
    public function existsOverlap(
        User $creator,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $excludeId = null
    ): bool {
        $qb = $this->createQueryBuilder('s')
            ->where('s.creator = :creator')
            ->andWhere('s.startTime < :end')
            ->andWhere('s.endTime > :start')
            ->setParameter('creator', $creator)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1);

        if ($excludeId) {
            $qb->andWhere('s.id != :id')
               ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * Find available (not booked) slots for a creator in a date range
     *
     * @return AvailabilitySlot[]
     */
    public function findAvailable(
        User $creator,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('s')
            ->where('s.creator = :creator')
            ->andWhere('s.isBooked = false')
            ->andWhere('s.startTime >= :from')
            ->andWhere('s.endTime <= :to')
            ->setParameter('creator', $creator)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find slots by creator in date range (booked or not)
     *
     * @return AvailabilitySlot[]
     */
    public function findByCreatorAndDateRange(
        User $creator,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('s')
            ->where('s.creator = :creator')
            ->andWhere('s.startTime >= :from')
            ->andWhere('s.endTime <= :to')
            ->setParameter('creator', $creator)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete all slots for a creator in a specific date range
     */
    public function deleteByCreatorAndDateRange(
        User $creator,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): int {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.creator = :creator')
            ->andWhere('s.startTime >= :from')
            ->andWhere('s.endTime <= :to')
            ->andWhere('s.isBooked = false') // Ne supprimer que les slots non-bookés
            ->setParameter('creator', $creator)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->execute();
    }

    public function findOverlaps(User $creator, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.creator = :creator')
            ->andWhere('s.startTime < :end')
            ->andWhere('s.endTime > :start')
            ->setParameters([
                'creator' => $creator,
                'start' => $start,
                'end' => $end,
            ])
            ->getQuery()
            ->getResult();
    }
}
