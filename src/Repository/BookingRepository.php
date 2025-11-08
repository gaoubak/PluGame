<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /** @return Booking[] */
    public function findUpcomingForCreator(string $creatorId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.creator = :c')->setParameter('c', $creatorId)
            ->andWhere('b.startTime > :now')->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.startTime', 'ASC')
            ->getQuery()->getResult();
    }
}
