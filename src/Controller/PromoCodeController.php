<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\PromoCodeRepository;
use App\Service\PromoCodeService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;

#[Route('/api/promo-codes')]
#[OA\Tag(name: 'Promo Codes')]
class PromoCodeController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PromoCodeRepository $promoCodeRepo,
        private readonly PromoCodeService $promoCodeService,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Create a new promo code (Creator only)
     */
    #[Route('/create', name: 'promo_code_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/promo-codes/create',
        summary: 'Create a new promo code (Creator only)',
        security: [['bearerAuth' => []]],
        tags: ['Promo Codes']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['code', 'discount_type', 'discount_value'],
            properties: [
                new OA\Property(property: 'code', type: 'string', description: 'Promo code (uppercase, alphanumeric, hyphens, underscores)', example: 'SUMMER2025'),
                new OA\Property(property: 'discount_type', type: 'string', enum: ['percentage', 'fixed_amount'], example: 'percentage'),
                new OA\Property(property: 'discount_value', type: 'integer', description: 'For percentage: 1-100, For fixed: amount in cents', example: 20),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Summer sale discount'),
                new OA\Property(property: 'max_uses', type: 'integer', nullable: true, description: 'Maximum total uses (null = unlimited)', example: 100),
                new OA\Property(property: 'max_uses_per_user', type: 'integer', nullable: true, description: 'Max uses per user (null = unlimited)', example: 1),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true, example: '2025-12-31T23:59:59Z'),
                new OA\Property(property: 'min_amount', type: 'integer', nullable: true, description: 'Minimum booking amount in cents', example: 5000),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Promo code created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'discount_display', type: 'string', example: '20%'),
                new OA\Property(property: 'stripe_coupon_id', type: 'string', nullable: true),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid input')]
    #[OA\Response(response: 403, description: 'Not a creator')]
    public function create(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        // 🔒 Only creators can create promo codes
        if (!in_array('ROLE_CREATOR', $user->getRoles(), true)) {
            throw ApiProblemException::forbidden('Only creators can create promo codes');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['code'], $data['discount_type'], $data['discount_value'])) {
            throw ApiProblemException::badRequest('Missing required fields: code, discount_type, discount_value');
        }

        // Validate discount type
        if (!in_array($data['discount_type'], ['percentage', 'fixed_amount'], true)) {
            throw ApiProblemException::badRequest('discount_type must be either "percentage" or "fixed_amount"');
        }

        // Validate discount value
        if ($data['discount_type'] === 'percentage' && ($data['discount_value'] < 1 || $data['discount_value'] > 100)) {
            throw ApiProblemException::badRequest('percentage discount must be between 1 and 100');
        }

        if ($data['discount_value'] <= 0) {
            throw ApiProblemException::badRequest('discount_value must be positive');
        }

        // Check if code already exists
        $existingCode = $this->promoCodeRepo->findActiveByCode($data['code']);
        if ($existingCode) {
            throw ApiProblemException::badRequest('Promo code already exists');
        }

        // Create promo code using service (handles Stripe integration)
        try {
            $promoCode = $this->promoCodeService->createPromoCode(
                creator: $user,
                code: $data['code'],
                discountType: $data['discount_type'],
                discountValue: $data['discount_value'],
                description: $data['description'] ?? null,
                maxUses: $data['max_uses'] ?? null,
                maxUsesPerUser: $data['max_uses_per_user'] ?? null,
                expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
                minAmount: $data['min_amount'] ?? null
            );
        } catch (\Exception $e) {
            throw ApiProblemException::internal('Failed to create promo code: ' . $e->getMessage());
        }

        return $this->createApiResponse([
            'id' => $promoCode->getId(),
            'code' => $promoCode->getCode(),
            'discount_type' => $promoCode->getDiscountType(),
            'discount_value' => $promoCode->getDiscountValue(),
            'discount_display' => $promoCode->getDiscountDisplay(),
            'description' => $promoCode->getDescription(),
            'max_uses' => $promoCode->getMaxUses(),
            'used_count' => $promoCode->getUsedCount(),
            'expires_at' => $promoCode->getExpiresAt()?->format(\DateTime::ATOM),
            'is_active' => $promoCode->isActive(),
            'stripe_coupon_id' => $promoCode->getStripeCouponId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * List all promo codes for the current creator
     */
    #[Route('/mine', name: 'promo_code_mine', methods: ['GET'])]
    #[OA\Get(
        path: '/api/promo-codes/mine',
        summary: 'List my promo codes (Creator only)',
        security: [['bearerAuth' => []]],
        tags: ['Promo Codes']
    )]
    public function mine(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        if (!in_array('ROLE_CREATOR', $user->getRoles(), true)) {
            throw ApiProblemException::forbidden('Only creators can view promo codes');
        }

        $promoCodes = $this->promoCodeRepo->findByCreator($user);

        $data = array_map(fn (PromoCode $code) => [
            'id' => $code->getId(),
            'code' => $code->getCode(),
            'discount_type' => $code->getDiscountType(),
            'discount_display' => $code->getDiscountDisplay(),
            'description' => $code->getDescription(),
            'max_uses' => $code->getMaxUses(),
            'used_count' => $code->getUsedCount(),
            'expires_at' => $code->getExpiresAt()?->format(\DateTime::ATOM),
            'is_active' => $code->isActive(),
            'is_valid' => $code->isValid(),
        ], $promoCodes);

        return $this->createApiResponse($data);
    }

    /**
     * Validate a promo code for a specific booking amount
     */
    #[Route('/validate', name: 'promo_code_validate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/promo-codes/validate',
        summary: 'Validate a promo code for a booking',
        security: [['bearerAuth' => []]],
        tags: ['Promo Codes']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['code', 'creator_id', 'amount'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'SUMMER2025'),
                new OA\Property(property: 'creator_id', type: 'string', format: 'uuid', description: 'Creator whose services are being booked'),
                new OA\Property(property: 'amount', type: 'integer', description: 'Booking amount in cents', example: 10000),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Promo code is valid',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'valid', type: 'boolean', example: true),
                new OA\Property(property: 'discount_amount', type: 'integer', description: 'Discount in cents', example: 2000),
                new OA\Property(property: 'final_amount', type: 'integer', description: 'Amount after discount in cents', example: 8000),
                new OA\Property(property: 'discount_display', type: 'string', example: '20%'),
            ]
        )
    )]
    public function validate(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['code'], $data['creator_id'], $data['amount'])) {
            throw ApiProblemException::badRequest('Missing required fields: code, creator_id, amount');
        }

        $result = $this->promoCodeService->validatePromoCode(
            code: $data['code'],
            creatorId: $data['creator_id'],
            user: $user,
            amount: (int) $data['amount']
        );

        return $this->createApiResponse($result);
    }

    /**
     * Deactivate a promo code
     */
    #[Route('/{id}/deactivate', name: 'promo_code_deactivate', methods: ['POST'])]
    public function deactivate(PromoCode $promoCode): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        // 🔒 Only the creator who owns the code can deactivate it
        if ($promoCode->getCreator() !== $user) {
            throw ApiProblemException::forbidden('You can only deactivate your own promo codes');
        }

        $promoCode->setIsActive(false);
        $this->em->flush();

        return $this->createApiResponse([
            'message' => 'Promo code deactivated successfully',
            'code' => $promoCode->getCode(),
        ]);
    }
}
