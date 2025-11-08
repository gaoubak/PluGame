<?php

namespace App\DTO\Booking;

use App\DTO\AbstractRequestDTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating a new booking
 *
 * Supports two modes:
 * - Mode A: Book specific slots (provide slotIds)
 * - Mode B: Ad-hoc booking (provide startTime, durationMin, creatorId)
 */
class CreateBookingDTO extends AbstractRequestDTO
{
    /**
     * Service offering ID
     */
    #[Assert\NotBlank(message: 'Service ID is required', groups: ['create'])]
    #[Assert\Uuid(message: 'Invalid service ID format')]
    public string $serviceId;

    /**
     * Mode A: Array of availability slot IDs to book
     */
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Uuid(message: 'Invalid slot ID format')
    ])]
    public ?array $slotIds = null;

    /**
     * Mode B: Creator user ID (for ad-hoc booking)
     */
    #[Assert\Uuid(message: 'Invalid creator ID format')]
    public ?string $creatorId = null;

    /**
     * Mode B: Start time (ISO 8601 format)
     */
    public ?\DateTimeImmutable $startTime = null;

    /**
     * Mode B: Duration in minutes
     */
    #[Assert\Positive(message: 'Duration must be positive')]
    #[Assert\LessThanOrEqual(480, message: 'Maximum duration is 8 hours (480 minutes)')]
    public ?int $durationMin = null;

    /**
     * Optional: Short location descriptor (e.g., "Zoom", "Studio A")
     */
    #[Assert\Length(max: 100, maxMessage: 'Location is too long')]
    public ?string $location = null;

    /**
     * Optional: Full address/location details
     */
    #[Assert\Length(max: 500, maxMessage: 'Location text is too long')]
    public ?string $locationText = null;

    /**
     * Optional: Booking notes
     */
    #[Assert\Length(max: 1000, maxMessage: 'Notes are too long')]
    public ?string $notes = null;

    /**
     * Optional: Tax percentage
     */
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Tax percent must be between 0 and 100')]
    public int $taxPercent = 0;

    /**
     * Custom populate to handle date conversion
     */
    protected function populate(array $data): void
    {
        parent::populate($data);

        // Convert startTime string to DateTimeImmutable
        if (isset($data['startTime']) && is_string($data['startTime'])) {
            try {
                $this->startTime = new \DateTimeImmutable($data['startTime']);
            } catch (\Exception $e) {
                $this->startTime = null; // Let validator handle the error
            }
        }
    }

    /**
     * Validate that either slotIds OR (creatorId + startTime + durationMin) are provided
     */
    #[Assert\IsTrue(
        message: 'Provide either slotIds[] OR (startTime + durationMin + creatorId)',
        groups: ['create']
    )]
    public function isModeValid(): bool
    {
        $hasModeA = !empty($this->slotIds);
        $hasModeB = $this->creatorId && $this->startTime && $this->durationMin;

        return $hasModeA || $hasModeB;
    }

    /**
     * Check if using Mode A (slot-based booking)
     */
    public function isSlotMode(): bool
    {
        return !empty($this->slotIds);
    }

    /**
     * Check if using Mode B (ad-hoc booking)
     */
    public function isAdHocMode(): bool
    {
        return $this->creatorId && $this->startTime && $this->durationMin;
    }
}
