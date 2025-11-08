<?php

// src/Repository/PayoutMethodRepository.php

namespace App\Repository;

use App\Entity\PayoutMethod;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PayoutMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayoutMethod::class);
    }

    /**
     * Get user's payout methods
     */
    public function getUserMethods(User $user): array
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('pm.isDefault', 'DESC')
            ->addOrderBy('pm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get default payout method
     */
    public function getDefaultMethod(User $user): ?PayoutMethod
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.user = :user')
            ->andWhere('pm.isDefault = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Set method as default (and unset others)
     */
    public function setAsDefault(PayoutMethod $method): void
    {
        // Unset all other defaults for this user
        $this->createQueryBuilder('pm')
            ->update()
            ->set('pm.isDefault', 'false')
            ->where('pm.user = :user')
            ->andWhere('pm.id != :id')
            ->setParameter('user', $method->getUser())
            ->setParameter('id', $method->getId())
            ->getQuery()
            ->execute();

        // Set this one as default
        $method->setIsDefault(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Get verified methods
     */
    public function getVerifiedMethods(User $user): array
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.user = :user')
            ->andWhere('pm.isVerified = true')
            ->setParameter('user', $user)
            ->orderBy('pm.isDefault', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count user's methods
     */
    public function countUserMethods(User $user): int
    {
        return (int) $this->createQueryBuilder('pm')
            ->select('COUNT(pm.id)')
            ->where('pm.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
