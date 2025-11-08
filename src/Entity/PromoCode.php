<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\Table(name: 'promo_codes')]
#[ORM\Index(columns: ['code'], name: 'idx_promo_code')]
#[ORM\Index(columns: ['creator_id'], name: 'idx_promo_creator')]
#[ORM\Index(columns: ['is_active'], name: 'idx_promo_active')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_promo_expires')]
#[ORM\HasLifecycleCallbacks]
class PromoCode
{
    use UuidId;
    use Timestamps;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9_-]+$/',
        message: 'Promo code must contain only uppercase letters, numbers, hyphens, and underscores'
    )]
    private string $code;

    /**
     * The creator who owns this promo code
     * Only this creator's services can use this code
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $creator;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: ['percentage', 'fixed_amount'])]
    private string $discountType;

    /**
     * For percentage: value between 1-100
     * For fixed_amount: value in cents (e.g., 1000 = $10.00)
     */
    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $discountValue;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    /**
     * Maximum number of times this code can be used (null = unlimited)
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxUses = null;

    /**
     * Current number of times this code has been used
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $usedCount = 0;

    /**
     * Maximum uses per user (null = unlimited)
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxUsesPerUser = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Stripe coupon ID (for Stripe integration)
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripeCouponId = null;

    /**
     * Minimum booking amount required to use this code (in cents)
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $minAmount = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code); // Always uppercase
        return $this;
    }

    public function getCreator(): User
    {
        return $this->creator;
    }

    public function setCreator(User $creator): self
    {
        $this->creator = $creator;
        return $this;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): self
    {
        $this->discountType = $discountType;
        return $this;
    }

    public function getDiscountValue(): int
    {
        return $this->discountValue;
    }

    public function setDiscountValue(int $discountValue): self
    {
        $this->discountValue = $discountValue;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): self
    {
        $this->maxUses = $maxUses;
        return $this;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function incrementUsedCount(): self
    {
        $this->usedCount++;
        return $this;
    }

    public function getMaxUsesPerUser(): ?int
    {
        return $this->maxUsesPerUser;
    }

    public function setMaxUsesPerUser(?int $maxUsesPerUser): self
    {
        $this->maxUsesPerUser = $maxUsesPerUser;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getStripeCouponId(): ?string
    {
        return $this->stripeCouponId;
    }

    public function setStripeCouponId(?string $stripeCouponId): self
    {
        $this->stripeCouponId = $stripeCouponId;
        return $this;
    }

    public function getMinAmount(): ?int
    {
        return $this->minAmount;
    }

    public function setMinAmount(?int $minAmount): self
    {
        $this->minAmount = $minAmount;
        return $this;
    }

    /**
     * Check if the promo code is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        if ($this->maxUses && $this->usedCount >= $this->maxUses) {
            return false;
        }

        return true;
    }

    /**
     * Check if code is valid for a specific booking amount
     */
    public function isValidForAmount(int $amount): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->minAmount && $amount < $this->minAmount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount in cents for a given booking amount
     */
    public function calculateDiscount(int $amount): int
    {
        if ($this->discountType === 'percentage') {
            return (int) round(($amount * $this->discountValue) / 100);
        }

        // Fixed amount discount
        return min($this->discountValue, $amount); // Don't discount more than the total
    }

    /**
     * Get discount as human-readable string
     */
    public function getDiscountDisplay(): string
    {
        if ($this->discountType === 'percentage') {
            return $this->discountValue . '%';
        }

        return '$' . number_format($this->discountValue / 100, 2);
    }
}
