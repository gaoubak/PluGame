<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bookmark::class);
    }

    /**
     * Find if user has bookmarked a target user
     */
    public function findByUserAndTarget(User $user, User $targetUser): ?Bookmark
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->andWhere('b.targetUser = :target')
            ->setParameter('user', $user)
            ->setParameter('target', $targetUser)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all bookmarks for a user
     */
    public function findByUser(User $user, ?string $collection = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC');

        if ($collection) {
            $qb->andWhere('b.collection = :collection')
                ->setParameter('collection', $collection);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get bookmarked users (for display)
     */
    public function findBookmarkedUsers(User $user, ?string $collection = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.targetUser)')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC');

        if ($collection) {
            $qb->andWhere('b.collection = :collection')
                ->setParameter('collection', $collection);
        }

        return $qb->getQuery()->getSingleColumnResult();
    }

    /**
     * Check if user has bookmarked multiple users
     */
    public function findBookmarkedUserIds(User $user, array $userIds): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.targetUser) as userId')
            ->where('b.user = :user')
            ->andWhere('b.targetUser IN (:userIds)')
            ->setParameter('user', $user)
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();

        return array_column($results, 'userId');
    }

    /**
     * Count bookmarks for user
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all collections for user
     */
    public function findCollectionsByUser(User $user): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('DISTINCT b.collection')
            ->where('b.user = :user')
            ->andWhere('b.collection IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('b.collection', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($results, 'collection');
    }
}
