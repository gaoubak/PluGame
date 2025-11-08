<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PromoCode;
use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class PromoCodeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PromoCodeRepository $promoCodeRepo,
        private readonly StripeClient $stripe
    ) {
    }

    /**
     * Create a new promo code with Stripe integration
     */
    public function createPromoCode(
        User $creator,
        string $code,
        string $discountType,
        int $discountValue,
        ?string $description = null,
        ?int $maxUses = null,
        ?int $maxUsesPerUser = null,
        ?\DateTimeImmutable $expiresAt = null,
        ?int $minAmount = null
    ): PromoCode {
        $promoCode = new PromoCode();
        $promoCode->setCode($code);
        $promoCode->setCreator($creator);
        $promoCode->setDiscountType($discountType);
        $promoCode->setDiscountValue($discountValue);
        $promoCode->setDescription($description);
        $promoCode->setMaxUses($maxUses);
        $promoCode->setMaxUsesPerUser($maxUsesPerUser);
        $promoCode->setExpiresAt($expiresAt);
        $promoCode->setMinAmount($minAmount);

        // Create Stripe coupon
        try {
            $stripeCoupon = $this->createStripeCoupon($promoCode);
            $promoCode->setStripeCouponId($stripeCoupon->id);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create Stripe coupon: ' . $e->getMessage());
        }

        $this->em->persist($promoCode);
        $this->em->flush();

        return $promoCode;
    }

    /**
     * Validate a promo code for a specific booking
     *
     * @return array{valid: bool, discount_amount?: int, final_amount?: int, discount_display?: string, error?: string}
     */
    public function validatePromoCode(
        string $code,
        string $creatorId,
        User $user,
        int $amount
    ): array {
        $promoCode = $this->promoCodeRepo->findActiveByCode($code);

        if (!$promoCode) {
            return [
                'valid' => false,
                'error' => 'Promo code not found or inactive',
            ];
        }

        // 🔒 Security: Verify the promo code belongs to the creator
        if ($promoCode->getCreator()->getId() !== $creatorId) {
            return [
                'valid' => false,
                'error' => 'This promo code is not valid for this creator',
            ];
        }

        // Check if code is valid (active, not expired, not max uses)
        if (!$promoCode->isValid()) {
            return [
                'valid' => false,
                'error' => 'Promo code is expired or has reached maximum uses',
            ];
        }

        // Check minimum amount
        if (!$promoCode->isValidForAmount($amount)) {
            $minAmount = $promoCode->getMinAmount();
            return [
                'valid' => false,
                'error' => sprintf(
                    'Minimum booking amount of $%.2f required to use this code',
                    $minAmount / 100
                ),
            ];
        }

        // Check per-user limit
        if ($promoCode->getMaxUsesPerUser()) {
            $usageCount = $this->promoCodeRepo->countUsageByUser($promoCode, $user);
            if ($usageCount >= $promoCode->getMaxUsesPerUser()) {
                return [
                    'valid' => false,
                    'error' => 'You have reached the maximum uses for this promo code',
                ];
            }
        }

        // Calculate discount
        $discountAmount = $promoCode->calculateDiscount($amount);
        $finalAmount = max(0, $amount - $discountAmount);

        return [
            'valid' => true,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'discount_display' => $promoCode->getDiscountDisplay(),
            'promo_code_id' => $promoCode->getId(),
            'stripe_coupon_id' => $promoCode->getStripeCouponId(),
        ];
    }

    /**
     * Create a Stripe coupon for the promo code
     */
    private function createStripeCoupon(PromoCode $promoCode): \Stripe\Coupon
    {
        $couponData = [
            'name' => $promoCode->getCode(),
            'id' => 'promo_' . strtolower($promoCode->getCode()) . '_' . uniqid(),
        ];

        if ($promoCode->getDiscountType() === 'percentage') {
            $couponData['percent_off'] = $promoCode->getDiscountValue();
        } else {
            $couponData['amount_off'] = $promoCode->getDiscountValue();
            $couponData['currency'] = 'usd'; // Adjust based on your currency
        }

        if ($promoCode->getMaxUses()) {
            $couponData['max_redemptions'] = $promoCode->getMaxUses();
        }

        if ($promoCode->getExpiresAt()) {
            $couponData['redeem_by'] = $promoCode->getExpiresAt()->getTimestamp();
        }

        return $this->stripe->coupons->create($couponData);
    }

    /**
     * Apply promo code to a payment intent
     */
    public function applyToPaymentIntent(string $paymentIntentId, string $stripeCouponId): void
    {
        try {
            // Update the payment intent to include the coupon
            $this->stripe->paymentIntents->update($paymentIntentId, [
                'metadata' => [
                    'coupon_id' => $stripeCouponId,
                ],
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to apply coupon to payment intent: ' . $e->getMessage());
        }
    }
}
