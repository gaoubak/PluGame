<?php

// src/Controller/StripeWebhookController.php - STRIPE WEBHOOKS HANDLER

namespace App\Controller;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Service\StripePayoutService;
use App\Service\MercurePublisher;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stripe')]
class StripeWebhookController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $em,
        private readonly StripePayoutService $stripePayoutService,
        private readonly MercurePublisher $mercurePublisher,
        private readonly LoggerInterface $logger,
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * ✅ Handle Stripe webhooks
     * POST /api/stripe/webhook
     *
     * Events handled:
     * - payment_intent.succeeded → Update booking payment status
     * - payment_intent.payment_failed → Mark payment as failed
     * - charge.refunded → Handle refunds
     * - transfer.created → Payout created confirmation
     * - transfer.failed → Payout failed
     */
    #[Route('/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        if (!$sigHeader) {
            $this->logger->error('Stripe webhook: Missing signature header');
            return new Response('Missing signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->stripeWebhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        // Log the event
        $this->logger->info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        // Handle the event
        try {
            match ($event->type) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
                'charge.refunded' => $this->handleChargeRefunded($event),
                'transfer.created' => $this->handleTransferCreated($event),
                'transfer.failed' => $this->handleTransferFailed($event),
                default => $this->logger->info('Unhandled webhook event', ['type' => $event->type]),
            };

            return new Response('Webhook handled', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook handler error', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new Response('Internal error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Handle successful payment
     */
    private function handlePaymentIntentSucceeded(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;
        $bookingId = $paymentIntent->metadata->booking_id ?? null;
        $paymentType = $paymentIntent->metadata->payment_type ?? 'deposit';

        if (!$bookingId) {
            $this->logger->warning('Payment succeeded but no booking_id in metadata', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            $this->logger->error('Booking not found for payment', [
                'booking_id' => $bookingId,
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        // Update booking based on payment type
        if ($paymentType === 'deposit') {
            // Initial deposit payment (30%)
            $booking->setDepositPaidAt(new \DateTimeImmutable());
            $booking->setStripePaymentIntentId($paymentIntent->id);
            $booking->setStatus('deposit_paid');

            $this->logger->info('Deposit payment completed', [
                'booking_id' => $bookingId,
                'amount' => $paymentIntent->amount / 100,
            ]);
        } elseif ($paymentType === 'remaining') {
            // Remaining payment (70%)
            $booking->setRemainingPaidAt(new \DateTimeImmutable());
            $booking->setStatus('remaining_paid');

            $this->logger->info('Remaining payment completed - Deliverables unlocked', [
                'booking_id' => $bookingId,
                'amount' => $paymentIntent->amount / 100,
            ]);
        }

        $this->em->flush();
    }

    /**
     * ✅ Handle failed payment
     */
    private function handlePaymentIntentFailed(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if (!$bookingId) {
            return;
        }

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return;
        }

        $this->logger->error('Payment failed', [
            'booking_id' => $bookingId,
            'payment_intent_id' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error?->message ?? 'Unknown error',
        ]);

        // Could send email notification to user here
    }

    /**
     * ✅ Handle refund
     */
    private function handleChargeRefunded(\Stripe\Event $event): void
    {
        $charge = $event->data->object;
        $paymentIntentId = $charge->payment_intent;

        // Find booking by payment intent
        $booking = $this->bookingRepository->findOneBy([
            'stripePaymentIntentId' => $paymentIntentId,
        ]);

        if (!$booking) {
            $this->logger->warning('Booking not found for refund', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $refundAmount = $charge->amount_refunded;
        $reason = $booking->getCancelReason() ?? 'Refund processed';

        $this->logger->info('Refund processed', [
            'booking_id' => $booking->getId(),
            'amount' => $refundAmount / 100,
            'payment_intent_id' => $paymentIntentId,
        ]);

        // Update booking status
        $booking->setStatus('refunded');
        $this->em->flush();

        // ✅ Publish Mercure notification to athlete
        try {
            $this->mercurePublisher->publishRefundCompleted(
                $booking,
                $refundAmount,
                $reason
            );
        } catch (\Exception $e) {
            error_log('Mercure publish failed: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Handle transfer created (payout to creator)
     */
    private function handleTransferCreated(\Stripe\Event $event): void
    {
        $transfer = $event->data->object;
        $bookingId = $transfer->metadata->booking_id ?? null;

        if (!$bookingId) {
            return;
        }

        $this->logger->info('Transfer created to creator', [
            'booking_id' => $bookingId,
            'transfer_id' => $transfer->id,
            'amount' => $transfer->amount / 100,
            'destination' => $transfer->destination,
        ]);
    }

    /**
     * ✅ Handle transfer failed
     */
    private function handleTransferFailed(\Stripe\Event $event): void
    {
        $transfer = $event->data->object;
        $bookingId = $transfer->metadata->booking_id ?? null;

        if (!$bookingId) {
            return;
        }

        $this->logger->error('Transfer failed', [
            'booking_id' => $bookingId,
            'transfer_id' => $transfer->id,
            'amount' => $transfer->amount / 100,
            'error' => $transfer->failure_message ?? 'Unknown error',
        ]);

        // Could send email notification to admin here
    }
}
