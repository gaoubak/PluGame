<?php

// src/Service/BankPayoutService.php - SEPA Bank Transfer via Stripe Payouts API

namespace App\Service;

use App\Entity\Booking;
use App\Entity\User;
use App\Repository\PayoutMethodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class BankPayoutService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PayoutMethodRepository $payoutMethodRepository,
        private readonly EmailService $emailService,
        string $stripeSecretKey,
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    /**
     * ✅ Process bank transfer payout to creator's IBAN
     *
     * Uses Stripe Payouts API to send SEPA transfer directly to creator's bank account
     */
    public function processBankPayout(Booking $booking): void
    {
        $creator = $booking->getCreator();
        $creatorAmountCents = $booking->getCreatorAmountCents();
        $currency = $booking->getCurrency() ?? 'eur';

        // Get creator's default bank account
        $payoutMethods = $this->payoutMethodRepository->getUserMethods($creator);
        $defaultMethod = null;

        foreach ($payoutMethods as $method) {
            if ($method->isDefault() && $method->getIban()) {
                $defaultMethod = $method;
                break;
            }
        }

        if (!$defaultMethod) {
            throw new \Exception('No default bank account found for creator');
        }

        try {
            // Create or retrieve Stripe external account for this IBAN
            $externalAccountId = $this->getOrCreateExternalAccount(
                $creator,
                $defaultMethod->getIban(),
                $defaultMethod->getBic(),
                $defaultMethod->getBankName()
            );

            // Create Stripe Payout
            $payout = $this->stripe->payouts->create([
                'amount' => $creatorAmountCents,
                'currency' => strtolower($currency),
                'destination' => $externalAccountId,
                'method' => 'standard', // Standard SEPA transfer (1-3 business days)
                'description' => "Payout for booking {$booking->getId()} - {$booking->getService()?->getTitle()}",
                'metadata' => [
                    'booking_id' => $booking->getId(),
                    'creator_id' => $creator->getId(),
                    'creator_email' => $creator->getEmail(),
                ],
            ]);

            // Update booking
            $booking->setPayoutCompletedAt(new \DateTimeImmutable());
            $booking->setStripeTransferId($payout->id);
            $booking->setStatus('payout_completed');
            $this->em->flush();

            // Send notification email
            $this->emailService->sendPayoutNotification(
                $creator->getEmail(),
                $booking,
                $creatorAmountCents / 100,
                strtoupper($currency)
            );

            error_log("SEPA payout created for booking {$booking->getId()}: {$payout->id} ({$creatorAmountCents} cents to IBAN ending {$defaultMethod->getAccountLast4()})");
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe payout failed: ' . $e->getMessage());
            throw new \Exception('Failed to process bank transfer: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Get or create Stripe external account (bank account) for IBAN
     */
    private function getOrCreateExternalAccount(User $creator, string $iban, ?string $bic, ?string $bankName): string
    {
        // Create a custom account if creator doesn't have one
        if (!$creator->getStripeCustomerId()) {
            $customer = $this->stripe->customers->create([
                'email' => $creator->getEmail(),
                'name' => $creator->getFullName() ?? $creator->getUsername(),
                'metadata' => [
                    'user_id' => $creator->getId(),
                ],
            ]);
            $creator->setStripeCustomerId($customer->id);
            $this->em->flush();
        }

        // Check if external account already exists for this IBAN
        $existingAccounts = $this->stripe->customers->allSources(
            $creator->getStripeCustomerId(),
            ['object' => 'bank_account', 'limit' => 10]
        );

        foreach ($existingAccounts->data as $account) {
            if ($account->last4 === substr(str_replace(' ', '', $iban), -4)) {
                return $account->id;
            }
        }

        // Create new external account
        $externalAccount = $this->stripe->customers->createSource(
            $creator->getStripeCustomerId(),
            [
                'source' => [
                    'object' => 'bank_account',
                    'country' => substr($iban, 0, 2), // First 2 chars of IBAN = country code
                    'currency' => 'eur',
                    'account_holder_name' => $creator->getFullName() ?? $creator->getUsername(),
                    'account_holder_type' => 'individual',
                    'account_number' => $iban,
                    'routing_number' => $bic, // BIC/SWIFT code
                ],
            ]
        );

        return $externalAccount->id;
    }

    /**
     * ✅ Process batch payouts for all pending bank transfers
     *
     * This can be called manually or via cron job to process all pending payouts
     */
    public function processPendingPayouts(): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        // Find all bookings with pending payouts
        $pendingBookings = $this->em->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->where('b.status = :status')
            ->andWhere('b.payoutCompletedAt IS NULL')
            ->andWhere('b.creatorAmountCents IS NOT NULL')
            ->setParameter('status', 'pending_payout')
            ->getQuery()
            ->getResult();

        foreach ($pendingBookings as $booking) {
            try {
                $this->processBankPayout($booking);
                $results['success'][] = $booking->getId();
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'booking_id' => $booking->getId(),
                    'error' => $e->getMessage(),
                ];
                error_log("Failed to process payout for booking {$booking->getId()}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    /**
     * ✅ Validate IBAN format
     */
    public function validateIban(string $iban): bool
    {
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Check length (15-34 characters)
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        // Check if starts with 2 letters (country code)
        if (!preg_match('/^[A-Z]{2}/', $iban)) {
            return false;
        }

        // Move first 4 characters to end and convert letters to numbers
        $moved = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';

        for ($i = 0; $i < strlen($moved); $i++) {
            $char = $moved[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        // Check if mod 97 = 1
        return bcmod($numeric, '97') === '1';
    }
}
