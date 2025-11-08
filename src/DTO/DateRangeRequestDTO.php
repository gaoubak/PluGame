<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Date Range Filter DTO
 *
 * For filtering records by date range (e.g., bookings between two dates).
 *
 * Usage:
 * ```php
 * #[Route('/api/bookings/filter', methods: ['GET'])]
 * public function filterByDate(Request $request, ValidatorInterface $validator): Response
 * {
 *     $dateFilter = DateRangeRequestDTO::fromQueryParams($request, $validator);
 *
 *     $bookings = $this->bookingRepo->createQueryBuilder('b')
 *         ->where('b.startTime >= :start')
 *         ->andWhere('b.startTime <= :end')
 *         ->setParameter('start', $dateFilter->startDate)
 *         ->setParameter('end', $dateFilter->endDate)
 *         ->getQuery()
 *         ->getResult();
 *
 *     return $this->json($bookings);
 * }
 * ```
 */
class DateRangeRequestDTO extends AbstractRequestDTO
{
    /**
     * Start date (ISO 8601 format: 2025-11-07T00:00:00Z)
     */
    #[Assert\NotBlank(message: 'Start date is required')]
    public ?\DateTimeImmutable $startDate = null;

    /**
     * End date (ISO 8601 format: 2025-11-07T23:59:59Z)
     */
    #[Assert\NotBlank(message: 'End date is required')]
    public ?\DateTimeImmutable $endDate = null;

    /**
     * Optional timezone (defaults to UTC)
     */
    #[Assert\Timezone(message: 'Invalid timezone')]
    public string $timezone = 'UTC';

    /**
     * Populate with date string conversion
     */
    protected function populate(array $data): void
    {
        // Handle startDate
        if (isset($data['startDate']) && is_string($data['startDate'])) {
            try {
                $this->startDate = new \DateTimeImmutable($data['startDate']);
            } catch (\Exception $e) {
                // Let validator handle the error
                $this->startDate = null;
            }
        }

        // Handle endDate
        if (isset($data['endDate']) && is_string($data['endDate'])) {
            try {
                $this->endDate = new \DateTimeImmutable($data['endDate']);
            } catch (\Exception $e) {
                // Let validator handle the error
                $this->endDate = null;
            }
        }

        // Handle timezone
        if (isset($data['timezone'])) {
            $this->timezone = $data['timezone'];
        }
    }

    /**
     * Validate that end date is after start date
     */
    #[Assert\IsTrue(message: 'End date must be after start date')]
    public function isEndAfterStart(): bool
    {
        if (!$this->startDate || !$this->endDate) {
            return true; // Let other validators handle null dates
        }

        return $this->endDate >= $this->startDate;
    }

    /**
     * Validate that date range is not too large (max 1 year)
     */
    #[Assert\IsTrue(message: 'Date range cannot exceed 365 days')]
    public function isRangeReasonable(): bool
    {
        if (!$this->startDate || !$this->endDate) {
            return true;
        }

        $diff = $this->startDate->diff($this->endDate);
        return $diff->days <= 365;
    }

    /**
     * Get number of days in range
     */
    public function getDayCount(): int
    {
        if (!$this->startDate || !$this->endDate) {
            return 0;
        }

        return (int) $this->startDate->diff($this->endDate)->days;
    }

    /**
     * Create from query parameters
     */
    public static function fromQueryParams(
        \Symfony\Component\HttpFoundation\Request $request,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator
    ): static {
        $data = [
            'startDate' => $request->query->get('startDate'),
            'endDate' => $request->query->get('endDate'),
            'timezone' => $request->query->get('timezone', 'UTC'),
        ];

        return static::fromArray($data, $validator);
    }
}
