<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Optimized User Repository with eager loading
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepositoryOptimized extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find user with profiles (NO N+1)
     */
    public function findByIdWithProfiles(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.creatorProfile', 'cp')->addSelect('cp')
            ->leftJoin('u.athleteProfile', 'ap')->addSelect('ap')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active creators with their profiles
     *
     * @return User[]
     */
    public function findAllCreatorsOptimized(int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.creatorProfile', 'cp')->addSelect('cp')
            ->where('u.isActive = true')
            ->andWhere('cp.id IS NOT NULL')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users with profiles (optimized)
     */
    public function searchOptimized(
        string $query,
        ?string $sport = null,
        ?string $location = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.creatorProfile', 'cp')->addSelect('cp')
            ->leftJoin('u.athleteProfile', 'ap')->addSelect('ap')
            ->where('u.isActive = true')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit);

        if ($query) {
            $qb->andWhere('u.username LIKE :query OR u.fullName LIKE :query OR u.bio LIKE :query')
               ->setParameter('query', "%{$query}%");
        }

        if ($sport) {
            $qb->andWhere('u.sport = :sport OR ap.sport = :sport')
               ->setParameter('sport', $sport);
        }

        if ($location) {
            $qb->andWhere('u.location = :location OR cp.baseCity = :location')
               ->setParameter('location', $location);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count search results (for pagination)
     */
    public function countSearchResults(
        string $query,
        ?string $sport = null,
        ?string $location = null
    ): int {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = true');

        if ($query) {
            $qb->andWhere('u.username LIKE :query OR u.fullName LIKE :query OR u.bio LIKE :query')
               ->setParameter('query', "%{$query}%");
        }

        if ($sport) {
            $qb->leftJoin('u.athleteProfile', 'ap')
               ->andWhere('u.sport = :sport OR ap.sport = :sport')
               ->setParameter('sport', $sport);
        }

        if ($location) {
            $qb->leftJoin('u.creatorProfile', 'cp')
               ->andWhere('u.location = :location OR cp.baseCity = :location')
               ->setParameter('location', $location);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
