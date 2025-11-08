<?php

// src/Repository/LikeRepository.php

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
     * Find if user has liked a post OR liked the creator represented by post_{userId}.
     * Returns Like|null
     */
    public function findByUserAndPostOrCreator(User $user, string $postId): ?Like
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user);

        // if post looks like "post_{userId}" try to match likedUser relation as well
        if (str_starts_with($postId, 'post_')) {
            $candidateId = substr($postId, 5);
            if (ctype_digit($candidateId)) {
                $qb->andWhere('(l.postId = :postId OR l.likedUser = :creatorId)');
                $qb->setParameter('postId', $postId);
                $qb->setParameter('creatorId', (int)$candidateId);
                return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
            }
        }

        // default: match by postId string column
        return $qb->andWhere('l.postId = :postId')
            ->setParameter('postId', $postId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count likes for a post or for a creator represented by post_{userId}
     */
    public function countByPostOrCreator(string $postId): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

        if (str_starts_with($postId, 'post_')) {
            $candidateId = substr($postId, 5);
            if (ctype_digit($candidateId)) {
                $qb->where('(l.postId = :postId OR l.likedUser = :creatorId)');
                $qb->setParameter('postId', $postId);
                $qb->setParameter('creatorId', (int)$candidateId);
                return (int)$qb->getQuery()->getSingleScalarResult();
            }
        }

        $qb->where('l.postId = :postId')->setParameter('postId', $postId);
        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get all posts liked by user (unchanged)
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
     * Check if user has liked multiple posts (expects array of strings)
     */
    public function findLikedPostIds(User $user, array $postIds): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('l.postId')
            ->where('l.user = :user')
            ->andWhere('l.postId IN (:postIds)')
            ->setParameter('user', $user)
            ->setParameter('postIds', $postIds)
            ->getQuery()
            ->getResult();

        return array_column($results, 'postId');
    }
}
