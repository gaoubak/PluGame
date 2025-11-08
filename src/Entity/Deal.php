<?php

namespace App\Entity;

use App\Repository\DealRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DealRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Deal
{
    use UuidId;
    use Timestamps;

    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_PROPOSED  = 'PROPOSED';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_REJECTED  = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_PAID      = 'PAID';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $proposer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $counterparty = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(options: ['unsigned' => true])]
    private int $amountCents = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceId = null;

    public function getProposer(): ?User
    {
        return $this->proposer;
    }
    public function setProposer(User $u): self
    {
        $this->proposer = $u;
        return $this;
    }

    public function getCounterparty(): ?User
    {
        return $this->counterparty;
    }
    public function setCounterparty(User $u): self
    {
        $this->counterparty = $u;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $s): self
    {
        $this->status = $s;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $c): self
    {
        $this->currency = $c;
        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }
    public function setAmountCents(int $a): self
    {
        $this->amountCents = max(0, $a);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $d): self
    {
        $this->description = $d;
        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }
    public function setStripeProductId(?string $id): self
    {
        $this->stripeProductId = $id;
        return $this;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }
    public function setStripePriceId(?string $id): self
    {
        $this->stripePriceId = $id;
        return $this;
    }
}
