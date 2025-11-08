<?php

// src/Entity/Like.php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: 'likes')]
#[ORM\UniqueConstraint(name: 'unique_user_target', columns: ['user_id', 'liked_user_id'])]
#[ORM\HasLifecycleCallbacks]
class Like
{
    use UuidId;
    use Timestamps;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['like:read'])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'liked_user_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['like:read'])]
    private User $likedUser;

    public function __construct(User $user, User $likedUser)
    {
        $this->user = $user;
        $this->likedUser = $likedUser;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getLikedUser(): User
    {
        return $this->likedUser;
    }

    public function setLikedUser(User $likedUser): self
    {
        $this->likedUser = $likedUser;
        return $this;
    }
}
