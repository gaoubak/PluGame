<?php

namespace App\Repository;

use App\Entity\Deal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
    }

    /** @return Deal[] */
    public function findPayableForUser(int $userId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.counterparty = :u')
            ->andWhere('d.status = :s')->setParameter('s', Deal::STATUS_VALIDATED)
            ->setParameter('u', $userId)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
