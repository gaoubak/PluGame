<?php

// src/Entity/WalletCredit.php

namespace App\Entity;

use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use App\Repository\WalletCreditRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WalletCreditRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WalletCredit
{
    use UuidId;
    use Timestamps;

    // Transaction types
    public const TYPE_PURCHASE = 'PURCHASE';
    public const TYPE_BONUS = 'BONUS';
    public const TYPE_REFUND = 'REFUND';
    public const TYPE_USAGE = 'USAGE';
    public const TYPE_EXPIRATION = 'EXPIRATION';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'walletCredits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Payment $payment = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'integer')]
    private int $amountCents;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isExpired = false;

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
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

    public function setAmountCents(int $amountCents): self
    {
        $this->amountCents = $amountCents;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (
            !in_array($type, [
            self::TYPE_PURCHASE,
            self::TYPE_BONUS,
            self::TYPE_REFUND,
            self::TYPE_USAGE,
            self::TYPE_EXPIRATION,
            ])
        ) {
            throw new \InvalidArgumentException("Invalid wallet credit type: $type");
        }
        $this->type = $type;
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

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->isExpired) {
            return true;
        }

        if ($this->expiresAt && $this->expiresAt < new \DateTime()) {
            $this->isExpired = true;
            return true;
        }

        return false;
    }

    public function setIsExpired(bool $isExpired): self
    {
        $this->isExpired = $isExpired;
        return $this;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_PURCHASE,
            self::TYPE_BONUS,
            self::TYPE_REFUND,
        ]);
    }

    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_USAGE,
            self::TYPE_EXPIRATION,
        ]);
    }

    public function getEffectiveAmount(): int
    {
        return $this->isCredit() ? $this->amountCents : -$this->amountCents;
    }

    public function getFormattedAmount(): string
    {
        $amount = $this->amountCents / 100;
        $sign = $this->isCredit() ? '+' : '-';
        return sprintf('%s%.2f €', $sign, abs($amount));
    }
}
