<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PromoCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCode>
 */
class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function findActiveByCode(string $code): ?PromoCode
    {
        return $this->createQueryBuilder('p')
            ->where('p.code = :code')
            ->andWhere('p.isActive = :active')
            ->setParameter('code', strtoupper($code))
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCreator(User $creator): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.creator = :creator')
            ->setParameter('creator', $creator)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByCreator(User $creator): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.creator = :creator')
            ->andWhere('p.isActive = :active')
            ->andWhere('(p.expiresAt IS NULL OR p.expiresAt > :now)')
            ->setParameter('creator', $creator)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count how many times a user has used a specific promo code
     */
    public function countUsageByUser(PromoCode $promoCode, User $user): int
    {
        // This would query the Payment table to count usage
        // For now, we'll implement this when we create the Payment tracking
        return 0;
    }
}
