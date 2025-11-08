<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use UuidId;
    use Timestamps;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_REFUNDED = 'REFUNDED';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['payment:read'])]
    private ?User $user = null;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['payment:read'])]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['payment:read', 'payment:write'])]
    private int $amountCents = 0;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
    #[Groups(['payment:read'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['payment:read'])]
    private string $paymentMethod = 'card';

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['payment:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['payment:read'])]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'stripe'])]
    #[Groups(['payment:read'])]
    private string $paymentGateway = 'stripe';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['payment:read'])]
    private ?array $metadata = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripeChargeId = null;

    /**
     * Promo code used for this payment
     */
    #[ORM\ManyToOne(targetEntity: PromoCode::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['payment:read'])]
    private ?PromoCode $promoCode = null;

    /**
     * Original amount before discount (in cents)
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['payment:read'])]
    private ?int $originalAmountCents = null;

    /**
     * Discount amount from promo code (in cents)
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['payment:read'])]
    private ?int $discountAmountCents = null;

    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }
    public function setAmountCents(int $amount): self
    {
        $this->amountCents = max(0, $amount);
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }
    public function setPaymentMethod(string $method): self
    {
        $this->paymentMethod = $method;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $validStatuses = [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REFUNDED];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid payment status');
        }
        $this->status = $status;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }
    public function setTransactionId(?string $id): self
    {
        $this->transactionId = $id;
        return $this;
    }

    public function getPaymentGateway(): string
    {
        return $this->paymentGateway;
    }
    public function setPaymentGateway(string $gateway): self
    {
        $this->paymentGateway = $gateway;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }
    public function setStripePaymentIntentId(?string $id): self
    {
        $this->stripePaymentIntentId = $id;
        return $this;
    }

    public function getStripeChargeId(): ?string
    {
        return $this->stripeChargeId;
    }
    public function setStripeChargeId(?string $id): self
    {
        $this->stripeChargeId = $id;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    #[Groups(['payment:read'])]
    public function getFormattedAmount(): string
    {
        return number_format($this->amountCents / 100, 2) . ' ' . $this->currency;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function setPromoCode(?PromoCode $promoCode): self
    {
        $this->promoCode = $promoCode;
        return $this;
    }

    public function getOriginalAmountCents(): ?int
    {
        return $this->originalAmountCents;
    }

    public function setOriginalAmountCents(?int $originalAmountCents): self
    {
        $this->originalAmountCents = $originalAmountCents;
        return $this;
    }

    public function getDiscountAmountCents(): ?int
    {
        return $this->discountAmountCents;
    }

    public function setDiscountAmountCents(?int $discountAmountCents): self
    {
        $this->discountAmountCents = $discountAmountCents;
        return $this;
    }

    /**
     * Check if this payment used a promo code
     */
    public function hasPromoCode(): bool
    {
        return $this->promoCode !== null;
    }

    /**
     * Get discount percentage if promo code was used
     */
    #[Groups(['payment:read'])]
    public function getDiscountPercentage(): ?float
    {
        if (!$this->hasPromoCode() || !$this->originalAmountCents || $this->originalAmountCents <= 0) {
            return null;
        }

        return ($this->discountAmountCents / $this->originalAmountCents) * 100;
    }
}
