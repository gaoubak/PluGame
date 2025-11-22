<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GiftCard;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GiftCard>
 */
class GiftCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GiftCard::class);
    }

    public function findActiveByCode(string $code): ?GiftCard
    {
        return $this->createQueryBuilder('g')
            ->where('g.code = :code')
            ->andWhere('g.isActive = :active')
            ->andWhere('g.currentBalance > 0')
            ->andWhere('(g.expiresAt IS NULL OR g.expiresAt > :now)')
            ->setParameter('code', strtoupper($code))
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.purchasedBy = :user OR g.redeemedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActivePurchasedByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.purchasedBy = :user')
            ->andWhere('g.isActive = :active')
            ->andWhere('g.currentBalance > 0')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredCards(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.expiresAt < :now')
            ->andWhere('g.isActive = :active')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}