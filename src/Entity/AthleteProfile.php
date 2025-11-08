<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\Timestamps;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'athlete_profiles')]
#[ORM\HasLifecycleCallbacks]
class AthleteProfile
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'athleteProfile')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:read'])]
    private string $displayName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $sport = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $level = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $bio = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $homeCity = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $coverPhoto = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read'])]
    private array $achievements = [];

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read'])]
    private array $stats = [];

    // ✅ Constructor requires User
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    // ✅ getId() returns user_id
    public function getId(): int
    {
        return $this->user->getId();
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

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getSport(): ?string
    {
        return $this->sport;
    }

    public function setSport(?string $sport): self
    {
        $this->sport = $sport;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getHomeCity(): ?string
    {
        return $this->homeCity;
    }

    public function setHomeCity(?string $homeCity): self
    {
        $this->homeCity = $homeCity;
        return $this;
    }

    public function getCoverPhoto(): ?string
    {
        return $this->coverPhoto;
    }

    public function setCoverPhoto(?string $coverPhoto): self
    {
        $this->coverPhoto = $coverPhoto;
        return $this;
    }

    public function getAchievements(): array
    {
        return $this->achievements;
    }

    public function setAchievements(array $achievements): self
    {
        $this->achievements = $achievements;
        return $this;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function setStats(array $stats): self
    {
        $this->stats = $stats;
        return $this;
    }
}
