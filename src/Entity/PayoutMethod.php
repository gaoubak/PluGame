<?php

// src/Entity/PayoutMethod.php

namespace App\Entity;

use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use App\Repository\PayoutMethodRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PayoutMethodRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PayoutMethod
{
    use UuidId;
    use Timestamps;

    // Payout method types
    public const TYPE_BANK_ACCOUNT = 'BANK_ACCOUNT';
    public const TYPE_PAYPAL = 'PAYPAL';
    public const TYPE_STRIPE_EXPRESS = 'STRIPE_EXPRESS';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'payoutMethods')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $accountLast4 = null;

    // IBAN for SEPA bank transfers (encrypted/hashed in production)
    #[ORM\Column(type: 'string', length: 34, nullable: true)]
    private ?string $iban = null;

    // BIC/SWIFT code for international transfers
    #[ORM\Column(type: 'string', length: 11, nullable: true)]
    private ?string $bic = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripeAccountId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripeBankAccountId = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isVerified = true;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (
            !in_array($type, [
            self::TYPE_BANK_ACCOUNT,
            self::TYPE_PAYPAL,
            self::TYPE_STRIPE_EXPRESS,
            ])
        ) {
            throw new \InvalidArgumentException("Invalid payout method type: $type");
        }
        $this->type = $type;
        return $this;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): self
    {
        $this->bankName = $bankName;
        return $this;
    }

    public function getAccountLast4(): ?string
    {
        return $this->accountLast4;
    }

    public function setAccountLast4(?string $accountLast4): self
    {
        $this->accountLast4 = $accountLast4;
        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): self
    {
        // Store only the last 4 characters in accountLast4 for display
        if ($iban) {
            $cleanIban = str_replace(' ', '', $iban);
            $this->accountLast4 = substr($cleanIban, -4);
        }
        $this->iban = $iban;
        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): self
    {
        $this->bic = $bic;
        return $this;
    }

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): self
    {
        $this->stripeAccountId = $stripeAccountId;
        return $this;
    }

    public function getStripeBankAccountId(): ?string
    {
        return $this->stripeBankAccountId;
    }

    public function setStripeBankAccountId(?string $stripeBankAccountId): self
    {
        $this->stripeBankAccountId = $stripeBankAccountId;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
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

    // ============================================
    // HELPER METHODS
    // ============================================

    public function getDisplayName(): string
    {
        return match ($this->type) {
            self::TYPE_BANK_ACCOUNT => $this->bankName
                ? "{$this->bankName} •••• {$this->accountLast4}"
                : "Bank Account •••• {$this->accountLast4}",
            self::TYPE_PAYPAL => "PayPal {$this->accountLast4}",
            self::TYPE_STRIPE_EXPRESS => "Stripe Express",
            default => "Payout Method",
        };
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'bankName' => $this->bankName,
            'accountLast4' => $this->accountLast4,
            'displayName' => $this->getDisplayName(),
            'isDefault' => $this->isDefault,
            'isVerified' => $this->isVerified,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
