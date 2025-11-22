<?php

// src/Controller/WalletController.php

namespace App\Controller;

use App\Entity\User;
use App\Service\WalletService;
use App\Service\Stripe\StripeService;
use App\Traits\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/wallet')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class WalletController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly StripeService $stripeService,
    ) {
    }

    /**
     * ✅ Get wallet balance
     */
    #[Route('/balance', name: 'wallet_balance', methods: ['GET'])]
    public function getBalance(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $balanceCents = $this->walletService->getBalance($user);

            return $this->createApiResponse([
                'balanceCents' => $balanceCents,
                'formattedBalance' => $this->walletService->getFormattedBalance($user),
                'currency' => 'EUR',
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to get balance: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Get transaction history
     */
    #[Route('/history', name: 'wallet_history', methods: ['GET'])]
    public function getHistory(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        try {
            $limit = min(100, max(1, (int) $request->query->get('limit', 50)));
            $transactions = $this->walletService->getHistory($user, $limit);

            $data = array_map(fn($tx) => [
                'id' => $tx->getId(),
                'amountCents' => $tx->getAmountCents(),
                'type' => $tx->getType(),
                'description' => $tx->getDescription(),
                'createdAt' => $tx->getCreatedAt()->format('c'),
                'expiresAt' => $tx->getExpiresAt()?->format('c'),
                'isExpired' => $tx->isExpired(),
                'isCredit' => $tx->isCredit(),
                'isDebit' => $tx->isDebit(),
            ], $transactions);

            return $this->createApiResponse($data);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to get history: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Purchase credits
     */
    #[Route('/purchase', name: 'wallet_purchase', methods: ['POST'])]
    public function purchaseCredits(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $amountCents = $data['amountCents'] ?? null;
        $paymentMethodId =  $user->getStripePaymentMethods() ?? $data['paymentMethod'] ;
        
        if (!$amountCents || $amountCents < 500) { // Minimum 5€
            return $this->createApiResponse([
                'message' => 'Minimum purchase is 5€',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Create payment intent for purchasing credits
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $user,
                $amountCents,
                'eur',
                null,
                [],
                $paymentMethodId
            );

            return $this->createApiResponse([
                'paymentIntentId' => $paymentIntent->id,
                'clientSecret' => $paymentIntent->client_secret,
                'amountCents' => $amountCents,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to purchase credits: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Confirm credit purchase (called after payment succeeds)
     */
    #[Route('/purchase/confirm', name: 'wallet_purchase_confirm', methods: ['POST'])]
    public function confirmPurchase(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $paymentIntentId = $data['paymentIntentId'] ?? null;

        if (!$paymentIntentId) {
            return $this->createApiResponse([
                'message' => 'Payment intent ID required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentIntent = $this->stripeService->getPaymentIntent($paymentIntentId);

            if ($paymentIntent->status === 'succeeded') {
                // Add credits with 1 year expiry
                $expiresAt = new \DateTime('+1 year');

                $credit = $this->walletService->addCredits(
                    $user,
                    $paymentIntent->amount,
                    \App\Entity\WalletCredit::TYPE_PURCHASE,
                    'Credit purchase',
                    null,
                    $expiresAt
                );

                return $this->createApiResponse([
                    'message' => 'Credits added successfully',
                    'credit' => [
                        'id' => $credit->getId(),
                        'amountCents' => $credit->getAmountCents(),
                        'newBalance' => $this->walletService->getBalance($user),
                    ],
                ]);
            }

            return $this->createApiResponse([
                'message' => 'Payment not completed',
                'status' => $paymentIntent->status,
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to confirm purchase: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
