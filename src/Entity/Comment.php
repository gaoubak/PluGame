<?php

// src/Entity/Comment.php - BACKEND COMMENT ENTITY

namespace App\Entity;

use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use App\Entity\Traits\SoftDeletable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'comments')]
#[ORM\HasLifecycleCallbacks]
class Comment
{
    use UuidId;
    use Timestamps;
    use SoftDeletable;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['comment:read'])]
    private ?User $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['comment:read'])]
    private ?User $user = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['comment:read', 'comment:write'])]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['comment:read'])]
    private ?Comment $parentComment = null;

    #[ORM\OneToMany(mappedBy: 'parentComment', targetEntity: Comment::class, cascade: ['remove'])]
    private Collection $replies;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'comments_likes')]
    #[Groups(['comment:read'])]
    private Collection $likedBy;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['comment:read'])]
    private int $likesCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['comment:read'])]
    private int $repliesCount = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isEdited = false;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
        $this->likedBy = new ArrayCollection();
    }

    public function getPost(): ?User
    {
        return $this->post;
    }

    public function setPost(?User $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getParentComment(): ?Comment
    {
        return $this->parentComment;
    }

    public function setParentComment(?Comment $parentComment): self
    {
        $this->parentComment = $parentComment;
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(Comment $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies[] = $reply;
            $reply->setParentComment($this);
        }
        return $this;
    }

    public function removeReply(Comment $reply): self
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParentComment() === $this) {
                $reply->setParentComment(null);
            }
        }
        return $this;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): self
    {
        $this->likesCount = $likesCount;
        return $this;
    }

    public function incrementLikesCount(): self
    {
        $this->likesCount++;
        return $this;
    }

    public function decrementLikesCount(): self
    {
        if ($this->likesCount > 0) {
            $this->likesCount--;
        }
        return $this;
    }

    public function getRepliesCount(): int
    {
        return $this->repliesCount;
    }

    public function setRepliesCount(int $repliesCount): self
    {
        $this->repliesCount = $repliesCount;
        return $this;
    }

    public function incrementRepliesCount(): self
    {
        $this->repliesCount++;
        return $this;
    }

    public function decrementRepliesCount(): self
    {
        if ($this->repliesCount > 0) {
            $this->repliesCount--;
        }
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getLikedBy(): Collection
    {
        return $this->likedBy;
    }

    public function addLike(User $user): self
    {
        if (!$this->likedBy->contains($user)) {
            $this->likedBy[] = $user;
            $this->incrementLikesCount();
        }
        return $this;
    }

    public function removeLike(User $user): self
    {
        if ($this->likedBy->removeElement($user)) {
            $this->decrementLikesCount();
        }
        return $this;
    }

    public function isLikedByUser(User $user): bool
    {
        return $this->likedBy->contains($user);
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    public function isEdited(): bool
    {
        return $this->isEdited;
    }

    public function setIsEdited(bool $isEdited): self
    {
        $this->isEdited = $isEdited;
        return $this;
    }

    // Helper methods
    public function isReply(): bool
    {
        return $this->parentComment !== null;
    }

    public function hasReplies(): bool
    {
        return $this->repliesCount > 0;
    }

    public function getTotalLikes(): int
    {
        return $this->likedBy->count();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user && $this->user->getId() === $user->getId();
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($this->user && $this->user->getId() === $user->getId()) {
            return true;
        }

        // Check if user is admin
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
