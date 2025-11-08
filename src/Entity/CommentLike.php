<?php

// src/Entity/CommentLike.php
namespace App\Entity;

use App\Repository\CommentLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentLikeRepository::class)]
#[ORM\Table(name: 'comment_likes')]
class CommentLike
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: 'App\Entity\Comment', inversedBy: 'likes')]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Comment $comment = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: 'commentLikes')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function __construct(Comment $comment, User $user)
    {
        $this->comment = $comment;
        $this->user = $user;
    }

    public function getComment(): ?\App\Entity\Comment
    {
        return $this->comment;
    }

    public function setComment(\App\Entity\Comment $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getUser(): ?\App\Entity\User
    {
        return $this->user;
    }

    public function setUser(\App\Entity\User $user): self
    {
        $this->user = $user;
        return $this;
    }
}
