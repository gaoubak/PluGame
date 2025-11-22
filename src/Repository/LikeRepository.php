<?php

namespace App\Repository;

use App\Entity\Like;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    /**
     * Find if user has liked a specific creator
     */
    public function findByUserAndLikedUser(User $user, User $likedUser): ?Like
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.likedUser = :likedUser')
            ->setParameter('user', $user)
            ->setParameter('likedUser', $likedUser)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count likes for a creator
     */
    public function countByLikedUser(User $likedUser): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.likedUser = :likedUser')
            ->setParameter('likedUser', $likedUser)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all likes by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user has liked multiple creators (by their IDs)
     */
    public function findLikedUserIds(User $user, array $userIds): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.likedUser) as likedUserId')
            ->where('l.user = :user')
            ->andWhere('l.likedUser IN (:userIds)')
            ->setParameter('user', $user)
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();

        return array_column($results, 'likedUserId');
    }
}