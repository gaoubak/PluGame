<?php

// src/Repository/CommentLikeRepository.php
namespace App\Repository;

use App\Entity\CommentLike;
use App\Entity\Comment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentLike::class);
    }

    public function exists(Comment $comment, User $user): bool
    {
        return (bool) $this->createQueryBuilder('cl')
            ->select('1')
            ->andWhere('cl.comment = :comment')
            ->andWhere('cl.user = :user')
            ->setParameters(['comment' => $comment, 'user' => $user])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function removeLike(Comment $comment, User $user): void
    {
        $qb = $this->_em->createQueryBuilder()
            ->delete(CommentLike::class, 'cl')
            ->andWhere('cl.comment = :comment')
            ->andWhere('cl.user = :user')
            ->setParameters(['comment' => $comment, 'user' => $user]);

        $qb->getQuery()->execute();
    }
}
