<?php

// src/Service/StripePayoutService.php - STRIPE CONNECT PAYOUT

namespace App\Service;

use App\Entity\Booking;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class StripePayoutService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailService $emailService,
        string $stripeSecretKey,
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    /**
     * ✅ Process payout to creator after deliverable download
     *
     * Flow:
     * 1. Calculate creator amount (after platform fee)
     * 2. Create Stripe Transfer to creator's Connect account
     * 3. Update booking status
     * 4. Send notification email
     */
    public function processBookingPayout(Booking $booking): void
    {
        // Check if already paid out
        if ($booking->getPayoutCompletedAt()) {
            throw new \Exception('Payout already completed for this booking');
        }

        $creator = $booking->getCreator();
        $totalAmountCents = $booking->getTotalAmountCents();
        $currency = $booking->getCurrency() ?? 'eur';

        // Verify creator has Stripe Connect account
        if (!$creator->getStripeAccountId()) {
            throw new \Exception('Creator does not have a Stripe Connect account');
        }

        // Calculate amounts
        $platformFeeCents = $this->calculatePlatformFee($totalAmountCents);
        $creatorAmountCents = $totalAmountCents - $platformFeeCents;

        // Get the original payment intent or charge
        $paymentIntentId = $booking->getStripePaymentIntentId();
        if (!$paymentIntentId) {
            throw new \Exception('No payment intent found for this booking');
        }

        try {
            // Create transfer to creator's Connect account
            $transfer = $this->stripe->transfers->create([
                'amount' => $creatorAmountCents,
                'currency' => $currency,
                'destination' => $creator->getStripeAccountId(),
                'description' => "Payout for booking {$booking->getId()} - {$booking->getService()->getTitle()}",
                'metadata' => [
                    'booking_id' => $booking->getId(),
                    'creator_id' => $creator->getId(),
                    'service_id' => $booking->getService()->getId(),
                ],
                'source_transaction' => $this->getChargeIdFromPaymentIntent($paymentIntentId),
            ]);

            // Update booking
            $booking->setPayoutCompletedAt(new \DateTimeImmutable());
            $booking->setStripeTransferId($transfer->id);
            $booking->setCreatorAmountCents($creatorAmountCents);
            $booking->setPlatformFeeCents($platformFeeCents);
            $this->em->flush();

            // Send notification email
            $this->emailService->sendPayoutNotification(
                $creator->getEmail(),
                $booking,
                $creatorAmountCents / 100,
                strtoupper($currency)
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe payout failed: ' . $e->getMessage());
            throw new \Exception('Failed to process payout: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Calculate platform fee (e.g., 15% of total)
     */
    private function calculatePlatformFee(int $totalAmountCents): int
    {
        // Platform takes 15%
        $feePercentage = 15;
        return (int) ($totalAmountCents * ($feePercentage / 100));
    }

    /**
     * ✅ Get charge ID from payment intent
     */
    private function getChargeIdFromPaymentIntent(string $paymentIntentId): ?string
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            return $paymentIntent->latest_charge ?? null;
        } catch (\Exception $e) {
            error_log('Failed to retrieve charge ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Create Stripe Connect Express account for creator
     */
    public function createConnectAccount(User $creator): string
    {
        if ($creator->getStripeAccountId()) {
            return $creator->getStripeAccountId();
        }

        try {
            $account = $this->stripe->accounts->create([
                'type' => 'express',
                'country' => 'FR',
                'email' => $creator->getEmail(),
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_type' => 'individual',
                'business_profile' => [
                    'name' => $creator->getCreatorProfile()?->getDisplayName() ?? $creator->getUsername(),
                ],
            ]);

            $creator->setStripeAccountId($account->id);
            $this->em->flush();

            return $account->id;
        } catch (\Exception $e) {
            throw new \Exception('Failed to create Stripe Connect account: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Generate onboarding link for creator
     */
    public function generateOnboardingLink(User $creator): string
    {
        $accountId = $creator->getStripeAccountId();
        if (!$accountId) {
            $accountId = $this->createConnectAccount($creator);
        }

        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $_ENV['APP_URL'] . '/profile/stripe/refresh',
                'return_url' => $_ENV['APP_URL'] . '/profile/stripe/complete',
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;
        } catch (\Exception $e) {
            throw new \Exception('Failed to generate onboarding link: ' . $e->getMessage());
        }
    }
}
