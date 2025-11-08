<?php

namespace App\Entity;

use App\Repository\BookingSegmentRepository;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BookingSegmentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BookingSegment
{
    use UuidId;

    #[ORM\ManyToOne(inversedBy: 'segments')]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: AvailabilitySlot::class)]
    #[ORM\JoinColumn(name: 'slot_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['booking:read'])]
    private ?AvailabilitySlot $slot = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['booking:read'])]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['booking:read'])]
    private \DateTimeImmutable $endTime;

    // Price for THIS segment (already computed)
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[Groups(['booking:read'])]
    private int $priceCents = 0;

    // Helper flag
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['booking:read'])]
    private bool $isFromSlot = false;

    public function __construct(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $priceCents = 0,
        ?AvailabilitySlot $slot = null
    ) {
        $this->startTime  = $start;
        $this->endTime    = $end;
        $this->priceCents = max(0, $priceCents);
        $this->slot       = $slot;
        $this->isFromSlot = $slot !== null;
    }

    // ---- relations ----
    public function getBooking(): ?Booking
    {
        return $this->booking;
    }
    public function setBooking(?Booking $b): self
    {
        $this->booking = $b;
        return $this;
    }

    public function getSlot(): ?AvailabilitySlot
    {
        return $this->slot;
    }
    public function setSlot(?AvailabilitySlot $slot): self
    {
        $this->slot = $slot;
        $this->isFromSlot = $slot !== null;
        return $this;
    }

    // ---- times ----
    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }
    public function setStartTime(\DateTimeImmutable $d): self
    {
        $this->startTime = $d;
        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }
    public function setEndTime(\DateTimeImmutable $d): self
    {
        $this->endTime = $d;
        return $this;
    }

    // ---- price ----
    public function getPriceCents(): int
    {
        return $this->priceCents;
    }
    public function setPriceCents(int $c): self
    {
        $this->priceCents = max(0, $c);
        return $this;
    }

    public function isFromSlot(): bool
    {
        return $this->isFromSlot;
    }
    public function setIsFromSlot(bool $b): self
    {
        $this->isFromSlot = $b;
        return $this;
    }
}
