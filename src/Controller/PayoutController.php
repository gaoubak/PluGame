<?php

// src/Controller/PayoutController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PayoutMethod;
use App\Entity\Payment;
use App\Repository\PayoutMethodRepository;
use App\Service\BankPayoutService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payouts')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PayoutController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PayoutMethodRepository $payoutMethodRepository,
        private readonly EntityManagerInterface $em,
        private readonly BankPayoutService $bankPayoutService,
    ) {
    }

    /**
     * ✅ Get payout methods
     */
    #[Route('/methods', name: 'payout_methods', methods: ['GET'])]
    public function getPayoutMethods(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $methods = $this->payoutMethodRepository->getUserMethods($user);

            $data = array_map(fn($method) => [
                'id' => $method->getId(),
                'type' => $method->getType(),
                'bankName' => $method->getBankName(),
                'accountLast4' => $method->getAccountLast4(),
                'displayName' => $method->getDisplayName(),
                'isDefault' => $method->isDefault(),
                'isVerified' => $method->isVerified(),
                'createdAt' => $method->getCreatedAt()->format('c'),
            ], $methods);

            return $this->createApiResponse($data);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to get payout methods: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Add payout method
     */
    #[Route('/methods', name: 'add_payout_method', methods: ['POST'])]
    public function addPayoutMethod(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $bankName = $data['bankName'] ?? null;
        $iban = $data['iban'] ?? null;
        $bic = $data['bic'] ?? null;

        if (!$bankName || !$iban) {
            return $this->createApiResponse([
                'message' => 'Bank name and IBAN required',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate IBAN format
        if (!$this->bankPayoutService->validateIban($iban)) {
            return $this->createApiResponse([
                'message' => 'Invalid IBAN format. Please check and try again.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $method = new PayoutMethod();
            $method->setUser($user);
            $method->setType(PayoutMethod::TYPE_BANK_ACCOUNT);
            $method->setBankName($bankName);
            $method->setIban($iban); // This will automatically set accountLast4
            $method->setBic($bic);

            // Set as default if first method
            $existingMethods = $this->payoutMethodRepository->getUserMethods($user);
            if (empty($existingMethods)) {
                $method->setIsDefault(true);
            }

            $this->em->persist($method);
            $this->em->flush();

            return $this->createApiResponse([
                'message' => 'Payout method added',
                'method' => [
                    'id' => $method->getId(),
                    'displayName' => $method->getDisplayName(),
                    'isDefault' => $method->isDefault(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to add payout method: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Set payout preference (stripe_connect or bank_transfer)
     */
    #[Route('/preference', name: 'set_payout_preference', methods: ['POST'])]
    public function setPayoutPreference(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->createApiResponse([
                'message' => 'Creator profile required',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $payoutMethod = $data['payoutMethod'] ?? null;

        if (!$payoutMethod || !in_array($payoutMethod, ['stripe_connect', 'bank_transfer'])) {
            return $this->createApiResponse([
                'message' => 'Invalid payout method. Must be "stripe_connect" or "bank_transfer"',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Validate based on chosen method
            if ($payoutMethod === 'stripe_connect' && !$user->getStripeAccountId()) {
                return $this->createApiResponse([
                    'message' => 'Stripe Connect account required. Please complete Stripe onboarding first.',
                    'action' => 'complete_stripe_onboarding',
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($payoutMethod === 'bank_transfer') {
                // Check if user has at least one bank account
                $bankAccounts = $this->payoutMethodRepository->getUserMethods($user);
                if (empty($bankAccounts)) {
                    return $this->createApiResponse([
                        'message' => 'Bank account required. Please add your IBAN first.',
                        'action' => 'add_bank_account',
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $user->setPayoutMethod($payoutMethod);
            $this->em->flush();

            return $this->createApiResponse([
                'message' => 'Payout preference updated successfully',
                'payoutMethod' => $user->getPayoutMethod(),
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to update payout preference: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Get current payout preference
     */
    #[Route('/preference', name: 'get_payout_preference', methods: ['GET'])]
    public function getPayoutPreference(#[CurrentUser] User $user): JsonResponse
    {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->createApiResponse([
                'message' => 'Creator profile required',
            ], Response::HTTP_FORBIDDEN);
        }

        $hasStripeConnect = $user->getStripeAccountId() !== null;
        $hasBankAccount = count($this->payoutMethodRepository->getUserMethods($user)) > 0;

        return $this->createApiResponse([
            'currentMethod' => $user->getPayoutMethod(),
            'availableMethods' => [
                'stripe_connect' => [
                    'available' => $hasStripeConnect,
                    'label' => 'Stripe Connect (Automatic)',
                    'description' => 'Instant transfer to your Stripe account',
                ],
                'bank_transfer' => [
                    'available' => $hasBankAccount,
                    'label' => 'Bank Transfer (SEPA)',
                    'description' => 'Direct transfer to your bank account',
                ],
            ],
        ]);
    }

    /**
     * ✅ Set default payout method
     */
    #[Route('/methods/{id}/set-default', name: 'set_default_payout_method', methods: ['POST'])]
    public function setDefaultPayoutMethod(
        PayoutMethod $method,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($method->getUser() !== $user) {
            return $this->createApiResponse([
                'message' => 'Not authorized',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Remove default from all other methods
            $allMethods = $this->payoutMethodRepository->getUserMethods($user);
            foreach ($allMethods as $m) {
                $m->setIsDefault(false);
            }

            $method->setIsDefault(true);
            $this->em->flush();

            return $this->createApiResponse([
                'message' => 'Default payout method updated',
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to set default: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Delete payout method
     */
    #[Route('/methods/{id}', name: 'delete_payout_method', methods: ['DELETE'])]
    public function deletePayoutMethod(
        PayoutMethod $method,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($method->getUser() !== $user) {
            return $this->createApiResponse([
                'message' => 'Not authorized',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->em->remove($method);
            $this->em->flush();

            return $this->createApiResponse([
                'message' => 'Payout method removed',
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to remove payout method: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Get revenues (for creators)
     *
     * This endpoint supports both payment flows:
     * 1. Legacy: Completed Payment entities (for backwards compatibility)
     * 2. New: Bookings with remainingPaidAt set (deposit/remaining payment flow)
     */
    #[Route('/revenues', name: 'get_revenues', methods: ['GET'])]
    public function getRevenues(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Check if user has creator profile
        if (!$user->getCreatorProfile()) {
            return $this->createApiResponse([
                'message' => 'Creator profile required',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $platformFeePercent = 15; // 15% platform fee
            $revenueData = [];
            $totalRevenue = 0;
            $totalEarned = 0;
            $totalPlatformFees = 0;

            // === APPROACH 1: Get completed Payment entities (legacy flow) ===
            $payments = $this->em->createQueryBuilder()
                ->select('p')
                ->from('App\Entity\Payment', 'p')
                ->join('p.booking', 'b')
                ->where('b.creator = :creator')
                ->andWhere('p.status = :status')
                ->setParameter('creator', $user)
                ->setParameter('status', Payment::STATUS_COMPLETED)
                ->orderBy('p.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            foreach ($payments as $payment) {
                $booking = $payment->getBooking();
                if (!$booking) {
                    continue;
                }

                $amountCents = $payment->getAmountCents();
                $platformFeeCents = (int) ($amountCents * ($platformFeePercent / 100));
                $creatorAmountCents = $amountCents - $platformFeeCents;

                $totalRevenue += $amountCents;
                $totalEarned += $creatorAmountCents;
                $totalPlatformFees += $platformFeeCents;

                $revenueData[] = [
                    'id' => $payment->getId(),
                    'amountCents' => $creatorAmountCents,
                    'totalCents' => $amountCents,
                    'platformFeeCents' => $platformFeeCents,
                    'isPaidOut' => $booking->getPayoutCompletedAt() !== null,
                    'createdAt' => $payment->getCreatedAt()->format('c'),
                    'booking' => [
                        'id' => $booking->getId(),
                        'service' => $booking->getService()?->getTitle(),
                        'athlete' => [
                            'id' => $booking->getAthlete()->getId(),
                            'username' => $booking->getAthlete()->getUsername(),
                        ],
                    ],
                ];
            }

            // === APPROACH 2: Get bookings with remainingPaidAt (new flow) ===
            // Only include bookings that don't already have a Payment entity
            $bookings = $this->em->createQueryBuilder()
                ->select('b')
                ->from('App\Entity\Booking', 'b')
                ->leftJoin('App\Entity\Payment', 'p', 'WITH', 'p.booking = b AND p.status = :status')
                ->where('b.creator = :creator')
                ->andWhere('b.remainingPaidAt IS NOT NULL')
                ->andWhere('p.id IS NULL') // Only bookings without Payment entity
                ->setParameter('creator', $user)
                ->setParameter('status', Payment::STATUS_COMPLETED)
                ->orderBy('b.remainingPaidAt', 'DESC')
                ->getQuery()
                ->getResult();

            foreach ($bookings as $booking) {
                $totalCents = $booking->getTotalCents();
                $platformFeeCents = (int) ($totalCents * ($platformFeePercent / 100));
                $creatorAmountCents = $totalCents - $platformFeeCents;

                $totalRevenue += $totalCents;
                $totalEarned += $creatorAmountCents;
                $totalPlatformFees += $platformFeeCents;

                $revenueData[] = [
                    'id' => $booking->getId(),
                    'amountCents' => $creatorAmountCents,
                    'totalCents' => $totalCents,
                    'platformFeeCents' => $platformFeeCents,
                    'isPaidOut' => $booking->getPayoutCompletedAt() !== null,
                    'createdAt' => $booking->getRemainingPaidAt()->format('c'),
                    'booking' => [
                        'id' => $booking->getId(),
                        'service' => $booking->getService()?->getTitle(),
                        'athlete' => [
                            'id' => $booking->getAthlete()->getId(),
                            'username' => $booking->getAthlete()->getUsername(),
                        ],
                    ],
                ];
            }

            // Sort all revenues by date
            usort($revenueData, function ($a, $b) {
                return strtotime($b['createdAt']) - strtotime($a['createdAt']);
            });

            return $this->createApiResponse([
                'totalRevenueCents' => $totalRevenue,
                'totalEarnedCents' => $totalEarned,
                'totalPlatformFeesCents' => $totalPlatformFees,
                'formattedRevenue' => number_format($totalRevenue / 100, 2) . ' €',
                'formattedEarned' => number_format($totalEarned / 100, 2) . ' €',
                'totalTransactions' => count($revenueData),
                'platformFeePercentage' => $platformFeePercent,
                'revenues' => $revenueData,
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to get revenues: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Process pending bank payouts (Admin/Cron)
     * POST /api/payouts/process-pending
     */
    #[Route('/process-pending', name: 'process_pending_payouts', methods: ['POST'])]
    public function processPendingPayouts(#[CurrentUser] User $user): JsonResponse
    {
        // Only admins can trigger batch processing
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->createApiResponse([
                'message' => 'Admin access required',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $results = $this->bankPayoutService->processPendingPayouts();

            return $this->createApiResponse([
                'message' => 'Batch payout processing completed',
                'processed' => count($results['success']),
                'failed' => count($results['failed']),
                'success' => $results['success'],
                'failures' => $results['failed'],
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to process payouts: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
