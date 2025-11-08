<?php

namespace App\Entity;

use App\Repository\AvailabilitySlotRepository;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AvailabilitySlotRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AvailabilitySlot
{
    use UuidId;

    #[ORM\ManyToOne(inversedBy: 'availability')]
    #[ORM\JoinColumn(name: 'creator_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?User $creator = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['slot:read','slot:write','user:read'])]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['slot:read','slot:write','user:read'])]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['slot:read','user:read'])]
    private bool $isBooked = false;

    // Optional link to the booking segment created from this slot
    #[ORM\OneToOne(mappedBy: 'slot', targetEntity: BookingSegment::class)]
    private ?BookingSegment $segment = null;

    public function __construct(User $creator, \DateTimeImmutable $start, \DateTimeImmutable $end)
    {
        $this->creator   = $creator;
        $this->startTime = $start;
        $this->endTime   = $end;
        $this->isBooked  = false;
    }

    // --- Creator ---
    public function getCreator(): User
    {
        return $this->creator;
    }

    public function setCreator(?User $u): self
    {
        $this->creator = $u;
        return $this;
    }

    // --- Times ---
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

    // --- Booking status ---
    public function isBooked(): bool
    {
        return $this->isBooked;
    }

    public function setIsBooked(bool $b): self
    {
        $this->isBooked = $b;
        return $this;
    }

    // --- Segment link ---
    public function getSegment(): ?BookingSegment
    {
        return $this->segment;
    }

    public function setSegment(?BookingSegment $segment): self
    {
        $this->segment = $segment;
        if ($segment && $segment->getSlot() !== $this) {
            $segment->setSlot($this);
        }
        return $this;
    }

    // --- Helpers ---
    public function getDurationMinutes(): int
    {
        return (int) ceil(($this->endTime->getTimestamp() - $this->startTime->getTimestamp()) / 60);
    }

    public function overlaps(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        return $this->startTime < $end && $this->endTime > $start;
    }
}
