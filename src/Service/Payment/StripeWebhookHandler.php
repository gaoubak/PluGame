<?php

namespace App\Service\Payment;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles Stripe webhook events
 */
class StripeWebhookHandler
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly WalletService $walletService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handlePaymentIntentSucceeded(object $paymentIntent): void
    {
        $payment = $this->paymentRepository->findOneBy([
            'stripePaymentIntentId' => $paymentIntent->id,
        ]);

        if (!$payment) {
            $this->logger->warning('Payment not found for successful payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        // Update payment status
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $payment->setStripeChargeId($paymentIntent->latest_charge);

        $metadata = $payment->getMetadata() ?? [];
        $booking = $payment->getBooking();

        // Handle wallet deduction
        if (isset($metadata['wallet_used']) && $metadata['wallet_used'] > 0 && $booking) {
            try {
                $this->walletService->useCredits(
                    $payment->getUser(),
                    $metadata['wallet_used'],
                    $booking
                );
            } catch (\Exception $e) {
                $this->logger->error('Failed to deduct wallet credits', [
                    'payment_id' => $payment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle deposit payment
        if ($booking && isset($metadata['is_deposit']) && $metadata['is_deposit']) {
            $booking->setDepositPaidAt(new \DateTimeImmutable());
            $booking->setStatus(Booking::STATUS_ACCEPTED);

            $this->logger->info('Deposit paid for booking', [
                'booking_id' => $booking->getId(),
                'payment_id' => $payment->getId(),
            ]);
        }

        // Handle remaining payment - unlock deliverables
        if ($booking && isset($metadata['payment_type']) && $metadata['payment_type'] === 'remaining') {
            $booking->setRemainingPaidAt(new \DateTimeImmutable());

            $this->logger->info('Remaining amount paid - deliverables unlocked', [
                'booking_id' => $booking->getId(),
                'payment_id' => $payment->getId(),
            ]);
        }

        $this->em->flush();

        $this->logger->info('Payment intent succeeded', [
            'payment_id' => $payment->getId(),
            'amount' => $payment->getAmountCents(),
        ]);
    }

    public function handlePaymentIntentFailed(object $paymentIntent): void
    {
        $payment = $this->paymentRepository->findOneBy([
            'stripePaymentIntentId' => $paymentIntent->id,
        ]);

        if (!$payment) {
            $this->logger->warning('Payment not found for failed payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $payment->setStatus(Payment::STATUS_FAILED);
        $this->em->flush();

        $this->logger->error('Payment intent failed', [
            'payment_id' => $payment->getId(),
            'amount' => $payment->getAmountCents(),
            'user_id' => $payment->getUser()->getId(),
        ]);
    }

    public function handlePaymentIntentCanceled(object $paymentIntent): void
    {
        $payment = $this->paymentRepository->findOneBy([
            'stripePaymentIntentId' => $paymentIntent->id,
        ]);

        if (!$payment) {
            return;
        }

        $payment->setStatus(Payment::STATUS_FAILED);
        $this->em->flush();

        $this->logger->info('Payment intent canceled', [
            'payment_id' => $payment->getId(),
        ]);
    }
}
