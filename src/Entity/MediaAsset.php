<?php

namespace App\Entity;

use App\Repository\MediaAssetRepository;
use App\Entity\Traits\UuidId;
use App\Entity\Traits\Timestamps;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MediaAssetRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['created_at'], name: 'idx_media_created')]
#[ORM\Index(columns: ['owner_id', 'created_at'], name: 'idx_media_owner_created')]
class MediaAsset
{
    use UuidId;
    use Timestamps;

    // Types / visibility (keep if useful)
    public const TYPE_IMAGE = 'IMAGE';
    public const TYPE_VIDEO = 'VIDEO';

    public const VIS_PUBLIC   = 'PUBLIC';
    public const VIS_UNLISTED = 'UNLISTED';
    public const VIS_PRIVATE  = 'PRIVATE';

    // Purposes used by controller
    public const PURPOSE_AVATAR              = 'AVATAR';
    public const PURPOSE_BOOKING_DELIVERABLE = 'BOOKING_DELIVERABLE';
    public const PURPOSE_CREATOR_FEED        = 'CREATOR_FEED';
    public const PURPOSE_ATHLETE_FEED        = 'ATHLETE_FEED';
    public const PURPOSE_MISC                = 'MISC';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'mediaAssets')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['media:read'])]
    private ?User $owner = null;

    // --- STORAGE KEYS (R2) ---
    #[ORM\Column(length: 1024)]
    #[Groups(['media:read'])]
    private string $storageKey;               // e.g. deliverables/vid-abc123.mp4

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['media:read', 'user:read', 'get_message', 'message:read'])]
    private ?string $publicUrl = null; // if bucket is public or you set a CDN domain

    // --- WHAT this asset is for ---
    #[ORM\Column(length: 32, options: ['default' => self::PURPOSE_MISC])]
    #[Groups(['media:read', 'user:read'])]
    private string $purpose = self::PURPOSE_MISC;

    // --- FILE INFO ---
    #[ORM\Column(length: 255)]
    #[Groups(['media:read'])]
    private string $filename;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['media:read', 'user:read'])]
    private ?string $contentType = null; // replaces "mime"

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    #[Groups(['media:read'])]
    private int $bytes = 0;

    // Optional media metadata (keep if useful)
    #[ORM\Column(length: 5, options: ['default' => self::TYPE_IMAGE])]
    #[Groups(['media:read', 'user:read'])]
    private string $type = self::TYPE_IMAGE;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['media:read', 'user:read'])]
    private ?int $width = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['media:read', 'user:read'])]
    private ?int $height = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['media:read', 'user:read'])]
    private ?int $durationSec = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['media:read', 'user:read'])]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['media:read', 'user:read'])]
    private ?string $caption = null;

    #[ORM\Column(length: 12, options: ['default' => self::VIS_PUBLIC])]
    #[Groups(['media:read', 'user:read'])]
    private string $visibility = self::VIS_PUBLIC;

    // --- Relations (optional but handy) ---
    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'deliverables')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: CreatorProfile::class, inversedBy: 'mediaAssets')]
    #[ORM\JoinColumn(
        name: 'creator_profile_user_id',   // <— column on media_asset table
        referencedColumnName: 'user_id',   // <— PK column on creator_profile
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?CreatorProfile $creatorProfile = null;

    #[ORM\ManyToOne(targetEntity: AthleteProfile::class, inversedBy: 'mediaAssets')]
    #[ORM\JoinColumn(
        name: 'athlete_profile_user_id',
        referencedColumnName: 'user_id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?AthleteProfile $athleteProfile = null;

    // === Getters / Setters ===
    public function getOwner(): ?User
    {
        return $this->owner;
    }
    public function setOwner(?User $u): self
    {
        $this->owner = $u;
        return $this;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }
    public function setStorageKey(string $k): self
    {
        $this->storageKey = $k;
        return $this;
    }

    public function getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }
    public function setPublicUrl(?string $url): self
    {
        $this->publicUrl = $url;
        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }
    public function setPurpose(string $p): self
    {
        $this->purpose = $p;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
    public function setFilename(string $f): self
    {
        $this->filename = $f;
        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
    public function setContentType(?string $ct): self
    {
        $this->contentType = $ct;
        return $this;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }
    public function setBytes(int $b): self
    {
        $this->bytes = $b;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $t): self
    {
        $this->type = $t;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }
    public function setWidth(?int $w): self
    {
        $this->width = $w;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }
    public function setHeight(?int $h): self
    {
        $this->height = $h;
        return $this;
    }

    public function getDurationSec(): ?int
    {
        return $this->durationSec;
    }
    public function setDurationSec(?int $s): self
    {
        $this->durationSec = $s;
        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }
    public function setThumbnailUrl(?string $u): self
    {
        $this->thumbnailUrl = $u;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }
    public function setCaption(?string $c): self
    {
        $this->caption = $c;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }
    public function setVisibility(string $v): self
    {
        $this->visibility = $v;
        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
    public function setBooking(?Booking $b): self
    {
        $this->booking = $b;
        return $this;
    }

    public function getCreatorProfile(): ?CreatorProfile
    {
        return $this->creatorProfile;
    }
    public function setCreatorProfile(?CreatorProfile $cp): self
    {
        $this->creatorProfile = $cp;
        return $this;
    }

    public function getAthleteProfile(): ?AthleteProfile
    {
        return $this->athleteProfile;
    }
    public function setAthleteProfile(?AthleteProfile $ap): self
    {
        $this->athleteProfile = $ap;
        return $this;
    }
}
