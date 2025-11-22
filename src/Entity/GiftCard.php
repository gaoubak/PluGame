<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use App\Repository\GiftCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GiftCardRepository::class)]
#[ORM\Table(name: 'gift_cards')]
#[ORM\Index(columns: ['code'], name: 'idx_gift_card_code')]
#[ORM\Index(columns: ['is_active'], name: 'idx_gift_card_active')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_gift_card_expires')]
#[ORM\HasLifecycleCallbacks]
class GiftCard
{
    use UuidId;
    use Timestamps;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 50)]
    #[Groups(['gift_card:read'])]
    private string $code;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    #[Groups(['gift_card:read'])]
    private int $initialBalance;

    #[ORM\Column(type: 'integer')]
    #[Groups(['gift_card:read'])]
    private int $currentBalance;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
    #[Groups(['gift_card:read'])]
    private string $currency = 'EUR';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $purchasedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $redeemedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['gift_card:read'])]
    private ?\DateTimeImmutable $redeemedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['gift_card:read'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['gift_card:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['gift_card:read'])]
    private ?string $message = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $recipientEmail = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['gift_card:read'])]
    private ?string $recipientName = null;

    public function __construct()
    {
        $this->generateCode();
    }

    private function generateCode(): void
    {
        $this->code = 'GIFT-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getInitialBalance(): int
    {
        return $this->initialBalance;
    }

    public function setInitialBalance(int $initialBalance): self
    {
        $this->initialBalance = $initialBalance;
        $this->currentBalance = $initialBalance;
        return $this;
    }

    public function getCurrentBalance(): int
    {
        return $this->currentBalance;
    }

    public function setCurrentBalance(int $currentBalance): self
    {
        $this->currentBalance = max(0, $currentBalance);
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getPurchasedBy(): ?User
    {
        return $this->purchasedBy;
    }

    public function setPurchasedBy(?User $purchasedBy): self
    {
        $this->purchasedBy = $purchasedBy;
        return $this;
    }

    public function getRedeemedBy(): ?User
    {
        return $this->redeemedBy;
    }

    public function setRedeemedBy(?User $redeemedBy): self
    {
        $this->redeemedBy = $redeemedBy;
        return $this;
    }

    public function getRedeemedAt(): ?\DateTimeImmutable
    {
        return $this->redeemedAt;
    }

    public function setRedeemedAt(?\DateTimeImmutable $redeemedAt): self
    {
        $this->redeemedAt = $redeemedAt;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(?string $recipientEmail): self
    {
        $this->recipientEmail = $recipientEmail;
        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): self
    {
        $this->recipientName = $recipientName;
        return $this;
    }

    public function isValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->currentBalance <= 0) {
            return false;
        }

        if ($this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function deduct(int $amount): int
    {
        $deductedAmount = min($amount, $this->currentBalance);
        $this->currentBalance -= $deductedAmount;

        if ($this->currentBalance <= 0) {
            $this->isActive = false;
        }

        return $deductedAmount;
    }

    public function redeem(User $user): self
    {
        if (!$this->redeemedBy) {
            $this->redeemedBy = $user;
            $this->redeemedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getBalanceDisplay(): string
    {
        return number_format($this->currentBalance / 100, 2) . ' ' . $this->currency;
    }
}