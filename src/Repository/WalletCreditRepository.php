<?php

// src/Repository/WalletCreditRepository.php

namespace App\Repository;

use App\Entity\WalletCredit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WalletCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletCredit::class);
    }

    /**
     * Get user's wallet balance
     */
    public function getUserBalance(User $user): int
    {
        $qb = $this->createQueryBuilder('wc');

        $result = $qb
            ->select('
                SUM(CASE WHEN wc.type IN (:creditTypes) THEN wc.amountCents ELSE 0 END) as credits,
                SUM(CASE WHEN wc.type IN (:debitTypes) THEN wc.amountCents ELSE 0 END) as debits
            ')
            ->where('wc.user = :user')
            ->andWhere('wc.isExpired = false')
            ->andWhere('(wc.expiresAt IS NULL OR wc.expiresAt > :now)')
            ->setParameter('user', $user)
            ->setParameter('creditTypes', [
                WalletCredit::TYPE_PURCHASE,
                WalletCredit::TYPE_BONUS,
                WalletCredit::TYPE_REFUND,
            ])
            ->setParameter('debitTypes', [
                WalletCredit::TYPE_USAGE,
            ])
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleResult();

        $credits = (int) ($result['credits'] ?? 0);
        $debits = (int) ($result['debits'] ?? 0);

        return $credits - $debits;
    }

    /**
     * Get user's credit history
     */
    public function getUserHistory(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('wc')
            ->where('wc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('wc.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get active credits (not expired)
     */
    public function getActiveCredits(User $user): array
    {
        return $this->createQueryBuilder('wc')
            ->where('wc.user = :user')
            ->andWhere('wc.type IN (:creditTypes)')
            ->andWhere('wc.isExpired = false')
            ->andWhere('(wc.expiresAt IS NULL OR wc.expiresAt > :now)')
            ->setParameter('user', $user)
            ->setParameter('creditTypes', [
                WalletCredit::TYPE_PURCHASE,
                WalletCredit::TYPE_BONUS,
                WalletCredit::TYPE_REFUND,
            ])
            ->setParameter('now', new \DateTime())
            ->orderBy('wc.expiresAt', 'ASC')
            ->addOrderBy('wc.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark expired credits
     */
    public function markExpiredCredits(): int
    {
        return $this->createQueryBuilder('wc')
            ->update()
            ->set('wc.isExpired', 'true')
            ->where('wc.expiresAt < :now')
            ->andWhere('wc.isExpired = false')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Get total purchased amount
     */
    public function getTotalPurchased(User $user): int
    {
        $result = $this->createQueryBuilder('wc')
            ->select('SUM(wc.amountCents) as total')
            ->where('wc.user = :user')
            ->andWhere('wc.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', WalletCredit::TYPE_PURCHASE)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}
