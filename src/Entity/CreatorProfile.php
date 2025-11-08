<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\Timestamps;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'creator_profiles')]
#[ORM\HasLifecycleCallbacks]
class CreatorProfile
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'creatorProfile')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:read', 'service:read'])]
    private string $displayName;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $bio = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read', 'service:read'])]
    private ?string $baseCity = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['user:read'])]
    private ?int $travelRadiusKm = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['user:read'])]
    private ?int $hourlyRateCents = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read'])]
    private array $gear = [];

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read'])]
    private array $specialties = [];

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $coverPhoto = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['user:read'])]
    private ?int $responseTime = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['user:read'])]
    private ?float $acceptanceRate = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['user:read'])]
    private ?float $completionRate = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $verified = false;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['user:read'])]
    private array $featuredWork = [];

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['user:read'])]
    private ?string $avgRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['user:read'])]
    private ?int $ratingsCount = null;

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

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getBaseCity(): ?string
    {
        return $this->baseCity;
    }

    public function setBaseCity(?string $baseCity): self
    {
        $this->baseCity = $baseCity;
        return $this;
    }

    public function getTravelRadiusKm(): ?int
    {
        return $this->travelRadiusKm;
    }

    public function setTravelRadiusKm(?int $travelRadiusKm): self
    {
        $this->travelRadiusKm = $travelRadiusKm;
        return $this;
    }

    public function getHourlyRateCents(): ?int
    {
        return $this->hourlyRateCents;
    }

    public function setHourlyRateCents(?int $hourlyRateCents): self
    {
        $this->hourlyRateCents = $hourlyRateCents;
        return $this;
    }

    public function getGear(): array
    {
        return $this->gear;
    }

    public function setGear(array $gear): self
    {
        $this->gear = $gear;
        return $this;
    }

    public function getSpecialties(): array
    {
        return $this->specialties;
    }

    public function setSpecialties(array $specialties): self
    {
        $this->specialties = $specialties;
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

    public function getResponseTime(): ?int
    {
        return $this->responseTime;
    }

    public function setResponseTime(?int $responseTime): self
    {
        $this->responseTime = $responseTime;
        return $this;
    }

    public function getAcceptanceRate(): ?float
    {
        return $this->acceptanceRate;
    }

    public function setAcceptanceRate(?float $acceptanceRate): self
    {
        $this->acceptanceRate = $acceptanceRate;
        return $this;
    }

    public function getCompletionRate(): ?float
    {
        return $this->completionRate;
    }

    public function setCompletionRate(?float $completionRate): self
    {
        $this->completionRate = $completionRate;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;
        return $this;
    }

    public function getFeaturedWork(): array
    {
        return $this->featuredWork;
    }

    public function setFeaturedWork(array $featuredWork): self
    {
        $this->featuredWork = $featuredWork;
        return $this;
    }

    public function getAvgRating(): ?string
    {
        return $this->avgRating;
    }

    public function setAvgRating(?string $avgRating): self
    {
        $this->avgRating = $avgRating;
        return $this;
    }

    public function getRatingsCount(): ?int
    {
        return $this->ratingsCount;
    }

    public function setRatingsCount(?int $ratingsCount): self
    {
        $this->ratingsCount = $ratingsCount;
        return $this;
    }
}
