<?php

namespace App\DTO\Booking;

use App\DTO\AbstractRequestDTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating an existing booking
 *
 * All fields are optional for partial updates (PATCH semantics).
 *
 * Usage:
 * ```php
 * $dto = UpdateBookingDTO::fromRequest($request, $validator, ['update']);
 * if ($dto->location !== null) {
 *     $booking->setLocation($dto->location);
 * }
 * ```
 */
class UpdateBookingDTO extends AbstractRequestDTO
{
    /**
     * Updated start time
     */
    public ?\DateTimeImmutable $startTime = null;

    /**
     * Updated duration
     */
    #[Assert\Positive(message: 'Duration must be positive', groups: ['update'])]
    #[Assert\LessThanOrEqual(480, message: 'Maximum duration is 8 hours', groups: ['update'])]
    public ?int $durationMin = null;

    /**
     * Updated location
     */
    #[Assert\Length(max: 100, maxMessage: 'Location is too long', groups: ['update'])]
    public ?string $location = null;

    /**
     * Updated location text
     */
    #[Assert\Length(max: 500, maxMessage: 'Location text is too long', groups: ['update'])]
    public ?string $locationText = null;

    /**
     * Updated notes
     */
    #[Assert\Length(max: 1000, maxMessage: 'Notes are too long', groups: ['update'])]
    public ?string $notes = null;

    /**
     * Custom populate to handle date conversion
     */
    protected function populate(array $data): void
    {
        // Handle startTime conversion
        if (isset($data['startTime']) && is_string($data['startTime'])) {
            try {
                $this->startTime = new \DateTimeImmutable($data['startTime']);
            } catch (\Exception $e) {
                $this->startTime = null;
            }
        }

        // Handle other fields
        if (isset($data['durationMin'])) {
            $this->durationMin = (int) $data['durationMin'];
        }
        if (isset($data['location'])) {
            $this->location = (string) $data['location'];
        }
        if (isset($data['locationText'])) {
            $this->locationText = (string) $data['locationText'];
        }
        if (isset($data['notes'])) {
            $this->notes = (string) $data['notes'];
        }
    }

    /**
     * Check if DTO has any updates
     */
    public function hasUpdates(): bool
    {
        return $this->startTime !== null
            || $this->durationMin !== null
            || $this->location !== null
            || $this->locationText !== null
            || $this->notes !== null;
    }

    /**
     * Apply updates to booking entity
     */
    public function applyTo(\App\Entity\Booking $booking): void
    {
        if ($this->location !== null) {
            $booking->setLocation($this->location);
        }
        if ($this->locationText !== null) {
            $booking->setLocationText($this->locationText);
        }
        if ($this->notes !== null) {
            $booking->setNotes($this->notes);
        }
        // Note: startTime and durationMin would need more complex logic
        // involving booking segments, so those are intentionally omitted
    }
}
