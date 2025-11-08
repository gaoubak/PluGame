<?php

namespace App\Entity;

use App\Entity\User;
use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'follower')]
#[ORM\UniqueConstraint(name: 'uniq_user_follower', columns: ['user_id', 'follower_id'])]
#[ORM\HasLifecycleCallbacks]
class Follower
{
    use UuidId;
    use Timestamps;

    // The user being followed
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['get_follower', 'get_chanel'])]
    private ?User $follower = null;

    // The follower (who follows $user)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'follower_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['get_follower', 'get_chanel', 'get_user', 'get_current_user_follower'])]
    private ?User $following = null;



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
