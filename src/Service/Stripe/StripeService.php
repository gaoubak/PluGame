<?php

namespace App\Service\Stripe;

use App\Entity\User;
use App\Entity\Booking;
use App\Entity\ServiceOffering;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Refund;

final class StripeService
{
    private StripeClient $stripe;
    private string $webhookSecret;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $publicBaseUrl,
        private readonly int $defaultTaxPercent,
        string $stripeSecretKey,
        string $stripeWebhookSecret = ''
    ) {
        if (empty($stripeSecretKey)) {
            throw new \InvalidArgumentException('Stripe API key cannot be empty');
        }

        $this->stripe = new StripeClient($stripeSecretKey);
        $this->webhookSecret = $stripeWebhookSecret;
    }

    /* ============================================
     * EXISTING METHODS (Your original code)
     * ============================================ */

    public function ensureCustomer(User $user): string
    {
        if ($user->getStripeCustomerId()) {
            return $user->getStripeCustomerId();
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->getEmail() ?: null,
            'name'  => $user->getUsername() ?: ('user#' . $user->getId()),
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->em->flush();

        return $customer->id;
    }

    public function syncServicePrice(ServiceOffering $service, bool $buyerIsPlugPlus = false): ServiceOffering
    {
        $calc = $this->computeTotals(
            baseCents: $service->getPriceCents(),
            quantityMinutes: $service->getDurationMin(),
            durationBlockMinutes: $service->getDurationMin(),
            buyerIsPlugPlus: $buyerIsPlugPlus
        );

        if (!$service->getStripeProductId()) {
            $product = $this->stripe->products->create([
                'name' => $service->getTitle(),
                'description' => $service->getDescription() ?? '',
            ]);
            $service->setStripeProductId($product->id);
        } else {
            $this->stripe->products->update($service->getStripeProductId(), [
                'name' => $service->getTitle(),
                'description' => $service->getDescription() ?? '',
            ]);
        }

        $price = $this->stripe->prices->create([
            'unit_amount' => $calc['totalCents'],
            'currency'    => 'eur',
            'product'     => $service->getStripeProductId(),
        ]);

        $service->setStripePriceId($price->id);
        $this->em->flush();

        return $service;
    }

    public function computeTotals(
        int $baseCents,
        int $quantityMinutes,
        int $durationBlockMinutes,
        bool $buyerIsPlugPlus = false
    ): array {
        $blocks = max(1, (int) ceil($quantityMinutes / $durationBlockMinutes));
        $subtotal = $blocks * $baseCents;

        $feePercent = $buyerIsPlugPlus ? 5 : 15;
        $fee = (int) round($subtotal * $feePercent / 100);
        $tax = (int) round(($subtotal + $fee) * $this->defaultTaxPercent / 100);
        $total = $subtotal + $fee + $tax;

        return [
            'blocks' => $blocks,
            'subtotalCents' => $subtotal,
            'feeCents' => $fee,
            'taxCents' => $tax,
            'totalCents' => $total,
            'feePercent' => $feePercent,
            'taxPercent' => $this->defaultTaxPercent,
        ];
    }

    /* ============================================
     * NEW METHODS (Payment System)
     * ============================================ */

    /**
     * Get or create Stripe customer
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        $customerId = $this->ensureCustomer($user);
        return $this->stripe->customers->retrieve($customerId);
    }

    /**
     * Create payment intent
     */
    public function createPaymentIntent(
        User $user,
        int $amountCents,
        string $currency = 'eur',
        ?Booking $booking = null,
        array $metadata = []
    ): PaymentIntent {
        $customerId = $this->ensureCustomer($user);

        $intentMetadata = array_merge(['user_id' => $user->getId()], $metadata);
        if ($booking) {
            $intentMetadata['booking_id'] = $booking->getId();
        }

        return $this->stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'customer' => $customerId,
            'metadata' => $intentMetadata,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Create setup intent for saving card
     */
    public function createSetupIntent(User $user): SetupIntent
    {
        $customerId = $this->ensureCustomer($user);

        return $this->stripe->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);
    }

    /**
     * Attach payment method to customer
     */
    public function attachPaymentMethod(User $user, string $paymentMethodId): PaymentMethod
    {
        $customerId = $this->ensureCustomer($user);

        $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);
        $paymentMethod->attach(['customer' => $customerId]);

        return $paymentMethod;
    }

    /**
     * Set default payment method
     */
    public function setDefaultPaymentMethod(User $user, string $paymentMethodId): Customer
    {
        $customerId = $this->ensureCustomer($user);

        return $this->stripe->customers->update($customerId, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);
    }

    /**
     * List payment methods
     */
    public function listPaymentMethods(User $user): array
    {
        $customerId = $this->ensureCustomer($user);

        $paymentMethods = $this->stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
        ]);

        return $paymentMethods->data;
    }

    /**
     * Delete payment method
     */
    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);
        return $paymentMethod->detach();
    }

    /**
     * Refund payment
     */
    public function refund(Payment $payment, ?int $amountCents = null): Refund
    {
        $refundData = [
            'payment_intent' => $payment->getStripePaymentIntentId(),
        ];

        if ($amountCents) {
            $refundData['amount'] = $amountCents;
        }

        return $this->stripe->refunds->create($refundData);
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $this->webhookSecret
        );
    }

    /**
     * Get payment intent
     */
    public function getPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Get payment method
     */
    public function getPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return $this->stripe->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * Format card for display
     */
    public function formatCard(PaymentMethod $paymentMethod): array
    {
        $card = $paymentMethod->card;

        return [
            'id' => $paymentMethod->id,
            'brand' => $card->brand,
            'last4' => $card->last4,
            'expMonth' => $card->exp_month,
            'expYear' => $card->exp_year,
        ];
    }
}
