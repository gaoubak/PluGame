<?php

// src/Controller/PaymentController.php - MERGED WITH DEPOSIT SYSTEM

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Booking;
use App\Repository\PaymentRepository;
use App\Repository\BookingRepository;
use App\Service\Stripe\StripeService;
use App\Service\WalletService;
use App\Service\MercurePublisher;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PaymentController extends AbstractController
{
    use ApiResponseTrait;

    private const DEPOSIT_PERCENTAGE = 30; // 30% upfront deposit

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly StripeService $stripeService,
        private readonly WalletService $walletService,
        private readonly EntityManagerInterface $em,
        private readonly MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * ✅ Create payment intent (supports wallet + partial payment)
     * POST /api/payments/intent
     */
    #[Route('/intent', name: 'create_payment_intent', methods: ['POST'])]
    public function createPaymentIntent(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $amountCents = $data['amountCents'] ?? null;
        $bookingId = $data['bookingId'] ?? null;
        $useWallet = $data['useWallet'] ?? false;
        $isDeposit = $data['isDeposit'] ?? false;

        if (!$amountCents || $amountCents <= 0) {
            return $this->errorResponse('Invalid amount', Response::HTTP_BAD_REQUEST);
        }

        try {
            $booking = null;
            if ($bookingId) {
                $booking = $this->bookingRepository->find($bookingId);
                if (!$booking) {
                    return $this->errorResponse('Booking not found', Response::HTTP_NOT_FOUND);
                }
            }

            // Calculate deposit if applicable
            $finalAmount = $amountCents;
            if ($isDeposit && $booking) {
                $finalAmount = (int) ($amountCents * (self::DEPOSIT_PERCENTAGE / 100));
                $booking->setDepositAmountCents($finalAmount);
                $booking->setRemainingAmountCents($amountCents - $finalAmount);
            }

            // Check wallet balance
            $walletBalance = $this->walletService->getBalance($user);
            $remainingAmount = $finalAmount;

            if ($useWallet && $walletBalance > 0) {
                $walletAmount = min($walletBalance, $finalAmount);
                $remainingAmount = $finalAmount - $walletAmount;
            }

            if ($remainingAmount > 0) {
                // Need to charge card for remaining amount
                $paymentIntent = $this->stripeService->createPaymentIntent(
                    $user,
                    $remainingAmount,
                    'eur',
                    $booking
                );

                $payment = new Payment();
                $payment->setUser($user);
                $payment->setBooking($booking);
                $payment->setAmountCents($finalAmount);
                $payment->setCurrency('EUR');
                $payment->setStatus(Payment::STATUS_PENDING);
                $payment->setPaymentGateway('stripe');
                $payment->setStripePaymentIntentId($paymentIntent->id);
                $payment->setMetadata([
                    'wallet_used' => $useWallet ? ($finalAmount - $remainingAmount) : 0,
                    'card_charged' => $remainingAmount,
                    'is_deposit' => $isDeposit,
                    'deposit_percentage' => $isDeposit ? self::DEPOSIT_PERCENTAGE : null,
                ]);

                $this->em->persist($payment);
                $this->em->flush();

                return $this->createApiResponse([
                    'paymentIntentId' => $paymentIntent->id,
                    'clientSecret' => $paymentIntent->client_secret,
                    'paymentId' => $payment->getId(),
                    'walletUsed' => $useWallet ? ($finalAmount - $remainingAmount) : 0,
                    'cardCharge' => $remainingAmount,
                    'isDeposit' => $isDeposit,
                    'depositAmount' => $isDeposit ? $finalAmount / 100 : null,
                    'remainingAmount' => $isDeposit ? ($amountCents - $finalAmount) / 100 : null,
                ], Response::HTTP_CREATED);
            } else {
                // Full wallet payment
                $payment = new Payment();
                $payment->setUser($user);
                $payment->setBooking($booking);
                $payment->setAmountCents($finalAmount);
                $payment->setCurrency('EUR');
                $payment->setStatus(Payment::STATUS_COMPLETED);
                $payment->setPaymentMethod('wallet');
                $payment->setPaymentGateway('wallet');
                $payment->setMetadata([
                    'is_deposit' => $isDeposit,
                ]);

                $this->em->persist($payment);

                // Deduct from wallet
                if ($booking) {
                    $this->walletService->useCredits($user, $finalAmount, $booking);
                    if ($isDeposit) {
                        $booking->setDepositPaidAt(new \DateTimeImmutable());
                    }
                }

                $this->em->flush();

                return $this->createApiResponse([
                    'paymentId' => $payment->getId(),
                    'walletUsed' => $finalAmount,
                    'cardCharge' => 0,
                    'message' => 'Paid with wallet',
                    'isDeposit' => $isDeposit,
                ], Response::HTTP_CREATED);
            }
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create payment: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * ✅ Pay remaining amount to unlock deliverables
     * POST /api/payments/pay-remaining/{bookingId}
     */
    #[Route('/pay-remaining/{id}', name: 'payment_pay_remaining', methods: ['POST'])]
    public function payRemaining(
        string $id,
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->notFoundResponse('Booking not found');
        }

        // Verify user is the athlete
        if ($booking->getAthlete()->getId() !== $user->getId()) {
            return $this->forbiddenResponse('Only the athlete can pay');
        }

        // Verify deposit was paid
        if (!$booking->getDepositPaidAt()) {
            return $this->errorResponse(
                'Deposit must be paid first',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Verify remaining amount not already paid
        if ($booking->getRemainingPaidAt()) {
            return $this->errorResponse(
                'Remaining amount already paid',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Verify deliverables exist
        if ($booking->getDeliverables()->count() === 0) {
            return $this->errorResponse(
                'No deliverables available yet',
                Response::HTTP_BAD_REQUEST
            );
        }

        $remainingAmountCents = $booking->getRemainingAmountCents();
        $data = json_decode($request->getContent(), true);
        $useWallet = $data['useWallet'] ?? false;

        try {
            // Check wallet balance
            $walletBalance = $this->walletService->getBalance($user);
            $cardCharge = $remainingAmountCents;

            if ($useWallet && $walletBalance > 0) {
                $walletAmount = min($walletBalance, $remainingAmountCents);
                $cardCharge = $remainingAmountCents - $walletAmount;
            }

            if ($cardCharge > 0) {
                // Create Payment Intent for remaining amount
                $paymentIntent = $this->stripeService->createPaymentIntent(
                    $user,
                    $cardCharge,
                    $booking->getCurrency() ?? 'eur',
                    $booking
                );

                $payment = new Payment();
                $payment->setUser($user);
                $payment->setBooking($booking);
                $payment->setAmountCents($remainingAmountCents);
                $payment->setCurrency($booking->getCurrency());
                $payment->setStatus(Payment::STATUS_PENDING);
                $payment->setPaymentGateway('stripe');
                $payment->setStripePaymentIntentId($paymentIntent->id);
                $payment->setMetadata([
                    'payment_type' => 'remaining',
                    'wallet_used' => $useWallet ? ($remainingAmountCents - $cardCharge) : 0,
                    'card_charged' => $cardCharge,
                ]);

                $this->em->persist($payment);
                $this->em->flush();

                return $this->createApiResponse([
                    'paymentIntentId' => $paymentIntent->id,
                    'clientSecret' => $paymentIntent->client_secret,
                    'paymentId' => $payment->getId(),
                    'remainingAmount' => $remainingAmountCents / 100,
                    'walletUsed' => $useWallet ? ($remainingAmountCents - $cardCharge) : 0,
                    'cardCharge' => $cardCharge / 100,
                ], Response::HTTP_OK);
            } else {
                // Full wallet payment
                $payment = new Payment();
                $payment->setUser($user);
                $payment->setBooking($booking);
                $payment->setAmountCents($remainingAmountCents);
                $payment->setCurrency($booking->getCurrency());
                $payment->setStatus(Payment::STATUS_COMPLETED);
                $payment->setPaymentMethod('wallet');
                $payment->setPaymentGateway('wallet');

                $this->em->persist($payment);

                // Deduct from wallet and unlock deliverables
                $this->walletService->useCredits($user, $remainingAmountCents, $booking);
                $booking->setRemainingPaidAt(new \DateTimeImmutable());

                $this->em->flush();

                return $this->createApiResponse([
                    'paymentId' => $payment->getId(),
                    'message' => 'Remaining paid with wallet - deliverables unlocked!',
                    'walletUsed' => $remainingAmountCents / 100,
                ], Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Payment initialization failed: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * ✅ Check payment status and deliverables unlock status
     * GET /api/payments/status/{bookingId}
     */
    #[Route('/status/{id}', name: 'payment_status', methods: ['GET'])]
    public function getStatus(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->notFoundResponse('Booking not found');
        }

        // Verify user is participant
        if (
            $booking->getCreator()->getId() !== $user->getId() &&
            $booking->getAthlete()->getId() !== $user->getId()
        ) {
            return $this->forbiddenResponse('Not authorized');
        }

        return $this->createApiResponse([
            'bookingId' => $booking->getId(),
            'totalAmount' => $booking->getTotalAmountCents() / 100,
            'depositAmount' => $booking->getDepositAmountCents() / 100,
            'remainingAmount' => $booking->getRemainingAmountCents() / 100,
            'depositPaid' => $booking->getDepositPaidAt() !== null,
            'depositPaidAt' => $booking->getDepositPaidAt()?->format(\DATE_ATOM),
            'remainingPaid' => $booking->getRemainingPaidAt() !== null,
            'remainingPaidAt' => $booking->getRemainingPaidAt()?->format(\DATE_ATOM),
            'deliverablesUnlocked' => $booking->getRemainingPaidAt() !== null,
            'deliverablesCount' => $booking->getDeliverables()->count(),
            'currency' => $booking->getCurrency(),
        ], Response::HTTP_OK);
    }

    /**
     * ✅ Get payment history
     * GET /api/payments/history
     */
    #[Route('/history', name: 'payment_history', methods: ['GET'])]
    public function getPaymentHistory(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $payments = $this->paymentRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        $data = array_map(fn($payment) => [
            'id' => $payment->getId(),
            'amountCents' => $payment->getAmountCents(),
            'currency' => $payment->getCurrency(),
            'status' => $payment->getStatus(),
            'paymentMethod' => $payment->getPaymentMethod(),
            'paymentGateway' => $payment->getPaymentGateway(),
            'createdAt' => $payment->getCreatedAt()->format('c'),
            'metadata' => $payment->getMetadata(),
            'booking' => $payment->getBooking() ? [
                'id' => $payment->getBooking()->getId(),
                'service' => $payment->getBooking()->getService()?->getTitle(),
            ] : null,
        ], $payments);

        return $this->createApiResponse($data);
    }

    /**
     * ✅ Stripe webhook handler
     * POST /api/payments/webhook
     */
    #[Route('/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        try {
            $event = $this->stripeService->verifyWebhookSignature($payload, $signature);

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;
            }

            return $this->createApiResponse(['received' => true]);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Webhook error: ' . $e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $payment = $this->paymentRepository->findOneBy([
            'stripePaymentIntentId' => $paymentIntent->id,
        ]);

        if ($payment) {
            $payment->setStatus(Payment::STATUS_COMPLETED);
            $payment->setStripeChargeId($paymentIntent->latest_charge);

            $metadata = $payment->getMetadata();
            $booking = $payment->getBooking();

            // Handle wallet deduction
            if (isset($metadata['wallet_used']) && $metadata['wallet_used'] > 0 && $booking) {
                $this->walletService->useCredits(
                    $payment->getUser(),
                    $metadata['wallet_used'],
                    $booking
                );
            }

            // Handle deposit payment
            if ($booking && isset($metadata['is_deposit']) && $metadata['is_deposit']) {
                $booking->setDepositPaidAt(new \DateTimeImmutable());
                $booking->setStatus(Booking::STATUS_ACCEPTED);
            }

            // Handle remaining payment - unlock deliverables
            if ($booking && isset($metadata['payment_type']) && $metadata['payment_type'] === 'remaining') {
                $booking->setRemainingPaidAt(new \DateTimeImmutable());
                // Deliverables are now unlocked!
            }

            $this->em->flush();

            // Publish Mercure notification
            try {
                $this->mercurePublisher->publishPaymentCompleted($payment);
            } catch (\Exception $e) {
                error_log('Mercure publish failed: ' . $e->getMessage());
            }
        }
    }

    private function handlePaymentIntentFailed($paymentIntent): void
    {
        $payment = $this->paymentRepository->findOneBy([
            'stripePaymentIntentId' => $paymentIntent->id,
        ]);

        if ($payment) {
            $payment->setStatus(Payment::STATUS_FAILED);
            $this->em->flush();

            // Publish Mercure notification
            try {
                $this->mercurePublisher->publishPaymentFailed($payment);
            } catch (\Exception $e) {
                error_log('Mercure publish failed: ' . $e->getMessage());
            }
        }
    }
}
