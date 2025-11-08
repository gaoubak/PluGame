<?php

namespace App\Service\Payment;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\User;
use App\Service\Stripe\StripeService;
use App\Service\WalletService;

/**
 * Factory for creating payment intents with wallet support
 */
class PaymentIntentFactory
{
    private const DEPOSIT_PERCENTAGE = 30;

    public function __construct(
        private readonly StripeService $stripeService,
        private readonly WalletService $walletService,
    ) {
    }

    public function createForBooking(
        User $user,
        int $amountCents,
        ?Booking $booking = null,
        bool $useWallet = false,
        bool $isDeposit = false
    ): PaymentIntentResult {
        // Calculate deposit if applicable
        $finalAmount = $isDeposit
            ? (int) ($amountCents * (self::DEPOSIT_PERCENTAGE / 100))
            : $amountCents;

        // Check wallet balance
        $walletBalance = $useWallet ? $this->walletService->getBalance($user) : 0;
        $walletAmount = min($walletBalance, $finalAmount);
        $remainingAmount = $finalAmount - $walletAmount;

        // Create payment entity
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setBooking($booking);
        $payment->setAmountCents($finalAmount);
        $payment->setCurrency('EUR');
        $payment->setStatus(Payment::STATUS_PENDING);

        $metadata = [
            'wallet_used' => $walletAmount,
            'card_charged' => $remainingAmount,
            'is_deposit' => $isDeposit,
        ];

        if ($isDeposit) {
            $metadata['deposit_percentage'] = self::DEPOSIT_PERCENTAGE;
        }

        // Full wallet payment
        if ($remainingAmount === 0) {
            $payment->setStatus(Payment::STATUS_COMPLETED);
            $payment->setPaymentMethod('wallet');
            $payment->setPaymentGateway('wallet');
            $payment->setMetadata($metadata);

            return new PaymentIntentResult(
                payment: $payment,
                paymentIntentId: null,
                clientSecret: null,
                walletUsed: $walletAmount,
                cardCharge: 0,
                isFullyPaidByWallet: true
            );
        }

        // Partial or full card payment
        $paymentIntent = $this->stripeService->createPaymentIntent(
            $user,
            $remainingAmount,
            'eur',
            $booking
        );

        $payment->setPaymentGateway('stripe');
        $payment->setStripePaymentIntentId($paymentIntent->id);
        $payment->setMetadata($metadata);

        return new PaymentIntentResult(
            payment: $payment,
            paymentIntentId: $paymentIntent->id,
            clientSecret: $paymentIntent->client_secret,
            walletUsed: $walletAmount,
            cardCharge: $remainingAmount,
            isFullyPaidByWallet: false
        );
    }

    public function getDepositPercentage(): int
    {
        return self::DEPOSIT_PERCENTAGE;
    }
}
