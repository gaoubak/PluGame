<?php

namespace App\Entity;

use App\Entity\Traits\UuidId;
use App\Repository\MediaDownloadTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaDownloadTokenRepository::class)]
#[ORM\HasLifecycleCallbacks]

class MediaDownloadToken
{
    use UuidId;

    #[ORM\ManyToOne(targetEntity: MediaAsset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaAsset $media;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'boolean')]
    private bool $used = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(MediaAsset $media, \DateTimeImmutable $expiresAt)
    {
        $this->media = $media;
        $this->expiresAt = $expiresAt;
    }

    public function getMedia(): MediaAsset
    {
        return $this->media;
    }

    /**
     * Alias for getMedia() - used in MediaDownloadController
     */
    public function getAsset(): MediaAsset
    {
        return $this->media;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $d): self
    {
        $this->expiresAt = $d;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markUsed(): self
    {
        $this->used = true;
        $this->usedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeImmutable $usedAt): self
    {
        $this->usedAt = $usedAt;
        $this->used = $usedAt !== null;
        return $this;
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }
}
