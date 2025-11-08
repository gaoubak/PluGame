<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Standardized Pagination Request DTO
 *
 * Use this for all paginated list endpoints to ensure consistency.
 *
 * Usage:
 * ```php
 * #[Route('/api/bookings', methods: ['GET'])]
 * public function list(Request $request, ValidatorInterface $validator): Response
 * {
 *     $pagination = PaginatedRequestDTO::fromRequest($request, $validator);
 *
 *     $bookings = $this->bookingRepo->findPaginated(
 *         page: $pagination->page,
 *         limit: $pagination->limit,
 *         sortBy: $pagination->sortBy,
 *         sortOrder: $pagination->sortOrder
 *     );
 *
 *     return $this->json($bookings);
 * }
 * ```
 */
class PaginatedRequestDTO extends AbstractRequestDTO
{
    /**
     * Page number (1-indexed)
     */
    #[Assert\Positive(message: 'Page must be a positive integer')]
    #[Assert\LessThan(10000, message: 'Page number too large')]
    public int $page = 1;

    /**
     * Items per page
     */
    #[Assert\Positive(message: 'Limit must be a positive integer')]
    #[Assert\LessThanOrEqual(100, message: 'Maximum limit is 100 items per page')]
    public int $limit = 20;

    /**
     * Field to sort by (e.g., 'createdAt', 'name')
     */
    #[Assert\Length(max: 50, maxMessage: 'Sort field name too long')]
    public ?string $sortBy = null;

    /**
     * Sort order: 'asc' or 'desc'
     */
    #[Assert\Choice(choices: ['asc', 'desc'], message: 'Sort order must be "asc" or "desc"')]
    public string $sortOrder = 'desc';

    /**
     * Search query string
     */
    #[Assert\Length(max: 255, maxMessage: 'Search query too long')]
    public ?string $search = null;

    /**
     * Get offset for database query
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * Create from query parameters instead of JSON body
     */
    public static function fromQueryParams(
        \Symfony\Component\HttpFoundation\Request $request,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator
    ): static {
        $data = [
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 20),
            'sortBy' => $request->query->get('sortBy'),
            'sortOrder' => $request->query->get('sortOrder', 'desc'),
            'search' => $request->query->get('search'),
        ];

        return static::fromArray($data, $validator);
    }
}
