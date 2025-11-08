<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'follow')]
#[ORM\UniqueConstraint(name: 'unique_follow', columns: ['follower_id', 'following_id'])]
#[ORM\HasLifecycleCallbacks]
class Follow
{
    use UuidId;
    use Timestamps;

    /**
     * User who follows (the follower)
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'follower_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['follow:read'])]
    private ?User $follower = null;

    /**
     * User being followed (the following)
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'following_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['follow:read'])]
    private ?User $following = null;

    // Getters and Setters

    public function getFollower(): ?User
    {
        return $this->follower;
    }

    public function setFollower(?User $follower): self
    {
        $this->follower = $follower;
        return $this;
    }

    public function getFollowing(): ?User
    {
        return $this->following;
    }

    public function setFollowing(?User $following): self
    {
        $this->following = $following;
        return $this;
    }
}
