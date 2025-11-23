<?php

// src/Entity/ServiceOffering.php

namespace App\Entity;

use App\Repository\ServiceOfferingRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use App\Entity\Traits\SoftDeletable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ServiceOfferingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ServiceOffering
{
    use UuidId;
    use Timestamps;
    use SoftDeletable;

    // relation to creator (User)
    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'creator_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    #[Groups(['service:read'])]
    private User $creator;

    // basic fields
    #[ORM\Column(type: 'string')]
    #[Groups(['service:read', 'service:write', 'user:read','booking:read'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['service:read','service:write', 'user:read','booking:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 60])]
    #[Groups(['service:read','service:write', 'user:read'])]
    private int $durationMin = 60;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['service:read','service:write', 'user:read','booking:read'])]
    private int $priceCents = 0;

    /**
     * Human readable deliverables (text)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['service:read','service:write', 'user:read'])]
    private ?string $deliverables = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['service:read','service:write', 'user:read'])]
    private bool $isActive = true;

    // Stripe references kept for integration
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['service:read'])]
    private ?string $stripeProductId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['service:read'])]
    private ?string $stripePriceId = null;

    // ---------------------------
    // NEW FIELDS
    // ---------------------------

    /**
     * Kind: 'HOURLY' | 'PER_ASSET' | 'PACKAGE'
     */
    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    #[Groups(['service:read','service:write','user:read'])]
    private ?string $kind = null;

    /**
     * For PER_ASSET: 'PHOTO' | 'VIDEO'
     */
    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    #[Groups(['service:read','service:write','user:read'])]
    private ?string $assetType = null;

    /**
     * For PER_ASSET: price per asset in cents
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['service:read','service:write','user:read','booking:read'])]
    private ?int $pricePerAssetCents = null;

    /**
     * For PACKAGE: total package price in cents
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['service:read','service:write','user:read','booking:read'])]
    private ?int $priceTotalCents = null;

    /**
     * For PACKAGE: structured includes (json), e.g. { "hours": 2, "photos": 20, "videos": 1 }
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['service:read','service:write','user:read'])]
    private ?array $includes = null;

    /**
     * Currency code (default EUR)
     */
    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
    #[Groups(['service:read','user:read'])]
    private string $currency = 'EUR';

    /**
     * Featured flag for frontend presentation
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['service:read','user:read'])]
    private bool $featured = false;

    public function __construct(User $creator)
    {
        $this->creator = $creator;
        // defaults already set on properties
    }

    // ---------------------------
    // Basic getters / setters
    // ---------------------------
    public function getCreator(): User
    {
        return $this->creator;
    }
    public function setCreator(?User $u): self
    {
        $this->creator = $u;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setTitle(string $t): self
    {
        $this->title = $t;
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

    public function getDurationMin(): int
    {
        return $this->durationMin;
    }
    public function setDurationMin(int $n): self
    {
        $this->durationMin = max(0, $n);
        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }
    public function setPriceCents(int $c): self
    {
        $this->priceCents = max(0, $c);
        return $this;
    }

    public function getDeliverables(): ?string
    {
        return $this->deliverables;
    }
    public function setDeliverables(?string $d): self
    {
        $this->deliverables = $d;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
    public function setIsActive(bool $b): self
    {
        $this->isActive = $b;
        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }
    public function setStripeProductId(?string $v): self
    {
        $this->stripeProductId = $v;
        return $this;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }
    public function setStripePriceId(?string $v): self
    {
        $this->stripePriceId = $v;
        return $this;
    }

    // ---------------------------
    // NEW fields getters / setters
    // ---------------------------

    public function getKind(): ?string
    {
        return $this->kind;
    }
    public function setKind(?string $k): self
    {
        $this->kind = $k !== null ? strtoupper($k) : null;
        return $this;
    }

    public function getAssetType(): ?string
    {
        return $this->assetType;
    }
    public function setAssetType(?string $asset): self
    {
        $this->assetType = $asset !== null ? strtoupper($asset) : null;
        return $this;
    }

    public function getPricePerAssetCents(): ?int
    {
        return $this->pricePerAssetCents;
    }
    public function setPricePerAssetCents(?int $c): self
    {
        $this->pricePerAssetCents = $c !== null ? max(0, $c) : null;
        return $this;
    }

    public function getPriceTotalCents(): ?int
    {
        return $this->priceTotalCents;
    }
    public function setPriceTotalCents(?int $c): self
    {
        $this->priceTotalCents = $c !== null ? max(0, $c) : null;
        return $this;
    }

    public function getIncludes(): ?array
    {
        return $this->includes;
    }
    public function setIncludes(?array $includes): self
    {
        $this->includes = $includes;
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

    public function isFeatured(): bool
    {
        return $this->featured;
    }
    public function setFeatured(bool $f): self
    {
        $this->featured = $f;
        return $this;
    }

    // ---------------------------
    // Helper convenience methods for frontend/backoffice
    // ---------------------------

    /**
     * Price in euros (float) for old code paths using priceCents
     */
    public function getPriceEuros(): float
    {
        return $this->priceCents / 100.0;
    }

    public function getPricePerAssetEuros(): ?float
    {
        return $this->pricePerAssetCents !== null ? $this->pricePerAssetCents / 100.0 : null;
    }

    public function getPriceTotalEuros(): ?float
    {
        return $this->priceTotalCents !== null ? $this->priceTotalCents / 100.0 : null;
    }

    /**
     * Fallback determination: is HOURLY if no kind provided
     */
    public function isHourly(): bool
    {
        return ($this->kind === null || strtoupper($this->kind) === 'HOURLY');
    }

    public function isPerAsset(): bool
    {
        return strtoupper((string)$this->kind) === 'PER_ASSET';
    }

    public function isPackage(): bool
    {
        return strtoupper((string)$this->kind) === 'PACKAGE';
    }
}
