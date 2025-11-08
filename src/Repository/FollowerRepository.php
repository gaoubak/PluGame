<?php

namespace App\Repository;

use App\Entity\Follower;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Follower>
 */
class FollowerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follower::class);
    }

    /**
     * Find a follower relationship between target and follower
     *
     * @param User $target The user being followed
     * @param User $follower The user who follows
     * @return Follower|null
     */
    public function findOneByUserAndFollower(User $target, User $follower): ?Follower
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :target')
            ->andWhere('f.follower = :follower')
            ->setParameter('target', $target)
            ->setParameter('follower', $follower)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if follower follows target
     *
     * @param User $target The user being followed
     * @param User $follower The user who follows
     * @return bool
     */
    public function isFollowing(User $target, User $follower): bool
    {
        return $this->findOneByUserAndFollower($target, $follower) !== null;
    }

    /**
     * Get all followers of a user (people who follow this user)
     *
     * @param User $user The user whose followers we want
     * @return Follower[]
     */
    public function findFollowersByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all users that this user is following
     *
     * @param User $follower The user who is following others
     * @return Follower[]
     */
    public function findFollowingByUser(User $follower): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.follower = :follower')
            ->setParameter('follower', $follower)
            ->orderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count how many followers a user has
     *
     * @param User $user
     * @return int
     */
    public function countFollowers(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count how many users this user is following
     *
     * @param User $follower
     * @return int
     */
    public function countFollowing(User $follower): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.follower = :follower')
            ->setParameter('follower', $follower)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get mutual followers (users who follow each other)
     *
     * @param User $user
     * @return array Array of User objects
     */
    public function findMutualFollowers(User $user): array
    {
        // Find users where:
        // - user follows them (f1.user = them, f1.follower = user)
        // - AND they follow user (f2.user = user, f2.follower = them)

        return $this->createQueryBuilder('f1')
            ->select('DISTINCT u')
            ->join('f1.user', 'u')
            ->join(Follower::class, 'f2', 'WITH', 'f2.user = :user AND f2.follower = u')
            ->where('f1.follower = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Remove all follower relationships for a user (when deleting user)
     *
     * @param User $user
     */
    public function removeAllByUser(User $user): void
    {
        // Remove where user is being followed
        $this->createQueryBuilder('f')
            ->delete()
            ->where('f.user = :user')
            ->orWhere('f.follower = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Get recent followers (last N followers)
     *
     * @param User $user
     * @param int $limit
     * @return Follower[]
     */
    public function findRecentFollowers(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findFollowerByUserAndFollowerId(User $user, User $followerId): ?Follower
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.follower = :followerId')
            ->setParameter('user', $user)
            ->setParameter('followerId', $followerId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
