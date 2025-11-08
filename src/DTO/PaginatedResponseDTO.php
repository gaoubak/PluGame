<?php

namespace App\DTO;

/**
 * Standardized Paginated Response DTO
 *
 * Use this to wrap all paginated API responses for consistency.
 *
 * Usage:
 * ```php
 * $pagination = PaginatedRequestDTO::fromQueryParams($request, $validator);
 * $bookings = $this->bookingRepo->findPaginated($pagination);
 * $total = $this->bookingRepo->countAll();
 *
 * $response = new PaginatedResponseDTO(
 *     data: $bookings,
 *     page: $pagination->page,
 *     limit: $pagination->limit,
 *     total: $total
 * );
 *
 * return $this->json($response->toArray());
 * ```
 *
 * Response format:
 * ```json
 * {
 *   "data": [...],
 *   "pagination": {
 *     "page": 2,
 *     "limit": 20,
 *     "total": 157,
 *     "totalPages": 8,
 *     "hasNext": true,
 *     "hasPrev": true
 *   }
 * }
 * ```
 */
class PaginatedResponseDTO
{
    public function __construct(
        private readonly array $data,
        private readonly int $page,
        private readonly int $limit,
        private readonly int $total,
    ) {
    }

    /**
     * Get total number of pages
     */
    public function getTotalPages(): int
    {
        if ($this->limit === 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->limit);
    }

    /**
     * Check if there's a next page
     */
    public function hasNext(): bool
    {
        return $this->page < $this->getTotalPages();
    }

    /**
     * Check if there's a previous page
     */
    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    /**
     * Get next page number (null if no next page)
     */
    public function getNextPage(): ?int
    {
        return $this->hasNext() ? $this->page + 1 : null;
    }

    /**
     * Get previous page number (null if no previous page)
     */
    public function getPrevPage(): ?int
    {
        return $this->hasPrev() ? $this->page - 1 : null;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'pagination' => [
                'page' => $this->page,
                'limit' => $this->limit,
                'total' => $this->total,
                'totalPages' => $this->getTotalPages(),
                'hasNext' => $this->hasNext(),
                'hasPrev' => $this->hasPrev(),
                'nextPage' => $this->getNextPage(),
                'prevPage' => $this->getPrevPage(),
            ],
        ];
    }

    /**
     * Factory: Create empty paginated response
     */
    public static function empty(int $page = 1, int $limit = 20): self
    {
        return new self(
            data: [],
            page: $page,
            limit: $limit,
            total: 0
        );
    }
}
