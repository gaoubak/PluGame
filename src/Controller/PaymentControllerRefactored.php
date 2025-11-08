<?php

namespace App\Controller;

use App\DTO\Payment\CreatePaymentIntentDTO;
use App\DTO\Payment\PayRemainingDTO;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentIntentFactory;
use App\Service\Payment\StripeWebhookHandler;
use App\Service\Stripe\StripeService;
use App\Service\WalletService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Refactored Payment Controller with clean architecture
 */
#[Route('/api/v1/payments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PaymentControllerRefactored extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly PaymentIntentFactory $paymentIntentFactory,
        private readonly StripeWebhookHandler $webhookHandler,
        private readonly StripeService $stripeService,
        private readonly WalletService $walletService,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Create payment intent
     * POST /api/v1/payments/intent
     */
    #[Route('/intent', name: 'v1_create_payment_intent', methods: ['POST'])]
    public function createPaymentIntent(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $dto = CreatePaymentIntentDTO::fromArray($data);

        // Validate DTO
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->createApiResponse([
                'errors' => $this->formatValidationErrors($errors),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Get booking if provided
            $booking = null;
            if ($dto->bookingId) {
                $booking = $this->bookingRepository->find($dto->bookingId);
                if (!$booking) {
                    return $this->createApiResponse([
                        'error' => 'Booking not found',
                    ], Response::HTTP_NOT_FOUND);
                }

                // Verify user owns this booking
                if ($booking->getAthlete()->getId() !== $user->getId()) {
                    return $this->createApiResponse([
                        'error' => 'Not authorized to pay for this booking',
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            // Create payment intent using factory
            $result = $this->paymentIntentFactory->createForBooking(
                user: $user,
                amountCents: $dto->amountCents,
                booking: $booking,
                useWallet: $dto->useWallet,
                isDeposit: $dto->isDeposit
            );

            // Persist payment
            $this->em->persist($result->payment);

            // Update booking if deposit
            if ($booking && $dto->isDeposit) {
                $depositAmount = (int) ($dto->amountCents * ($this->paymentIntentFactory->getDepositPercentage() / 100));
                $booking->setDepositAmountCents($depositAmount);
                $booking->setRemainingAmountCents($dto->amountCents - $depositAmount);

                // If fully paid by wallet, mark as paid
                if ($result->isFullyPaidByWallet) {
                    $booking->setDepositPaidAt(new \DateTimeImmutable());
                    $this->walletService->useCredits($user, $depositAmount, $booking);
                }
            }

            $this->em->flush();

            return $this->createApiResponse(
                array_merge($result->toArray(), [
                    'isDeposit' => $dto->isDeposit,
                    'depositPercentage' => $dto->isDeposit ? $this->paymentIntentFactory->getDepositPercentage() : null,
                ]),
                $result->isFullyPaidByWallet ? Response::HTTP_OK : Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'error' => 'Failed to create payment intent',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pay remaining amount to unlock deliverables
     * POST /api/v1/payments/pay-remaining/{id}
     */
    #[Route('/pay-remaining/{id}', name: 'v1_payment_pay_remaining', methods: ['POST'])]
    public function payRemaining(
        string $id,
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->createApiResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        // Authorization check
        if ($booking->getAthlete()->getId() !== $user->getId()) {
            return $this->createApiResponse(['error' => 'Only the athlete can pay'], Response::HTTP_FORBIDDEN);
        }

        // Validation checks
        if (!$booking->getDepositPaidAt()) {
            return $this->createApiResponse(['error' => 'Deposit must be paid first'], Response::HTTP_BAD_REQUEST);
        }

        if ($booking->getRemainingPaidAt()) {
            return $this->createApiResponse(['error' => 'Remaining amount already paid'], Response::HTTP_BAD_REQUEST);
        }

        if ($booking->getDeliverables()->count() === 0) {
            return $this->createApiResponse(['error' => 'No deliverables available yet'], Response::HTTP_BAD_REQUEST);
        }

        // Parse request
        $data = json_decode($request->getContent(), true) ?? [];
        $dto = PayRemainingDTO::fromArray($data);

        try {
            $remainingAmountCents = $booking->getRemainingAmountCents();

            // Create payment intent
            $result = $this->paymentIntentFactory->createForBooking(
                user: $user,
                amountCents: $remainingAmountCents,
                booking: $booking,
                useWallet: $dto->useWallet,
                isDeposit: false
            );

            // Add metadata for remaining payment
            $metadata = $result->payment->getMetadata() ?? [];
            $metadata['payment_type'] = 'remaining';
            $result->payment->setMetadata($metadata);

            $this->em->persist($result->payment);

            // If fully paid by wallet, unlock deliverables immediately
            if ($result->isFullyPaidByWallet) {
                $this->walletService->useCredits($user, $remainingAmountCents, $booking);
                $booking->setRemainingPaidAt(new \DateTimeImmutable());
            }

            $this->em->flush();

            return $this->createApiResponse(
                array_merge($result->toArray(), [
                    'deliverablesUnlocked' => $result->isFullyPaidByWallet,
                    'message' => $result->isFullyPaidByWallet ? 'Deliverables unlocked!' : 'Complete payment to unlock deliverables',
                ]),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get payment status for a booking
     * GET /api/v1/payments/status/{id}
     */
    #[Route('/status/{id}', name: 'v1_payment_status', methods: ['GET'])]
    public function getStatus(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->createApiResponse(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        // Authorization check
        if (
            $booking->getCreator()->getId() !== $user->getId() &&
            $booking->getAthlete()->getId() !== $user->getId()
        ) {
            return $this->createApiResponse(['error' => 'Not authorized'], Response::HTTP_FORBIDDEN);
        }

        return $this->createApiResponse([
            'bookingId' => $booking->getId(),
            'totalAmount' => $booking->getTotalAmountCents() / 100,
            'depositAmount' => $booking->getDepositAmountCents() ? $booking->getDepositAmountCents() / 100 : null,
            'remainingAmount' => $booking->getRemainingAmountCents() ? $booking->getRemainingAmountCents() / 100 : null,
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
     * Get payment history for current user
     * GET /api/v1/payments/history
     */
    #[Route('/history', name: 'v1_payment_history', methods: ['GET'])]
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
     * Stripe webhook handler
     * POST /api/v1/payments/webhook
     */
    #[Route('/webhook', name: 'v1_stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        try {
            $event = $this->stripeService->verifyWebhookSignature($payload, $signature);

            match ($event->type) {
                'payment_intent.succeeded' => $this->webhookHandler->handlePaymentIntentSucceeded($event->data->object),
                'payment_intent.payment_failed' => $this->webhookHandler->handlePaymentIntentFailed($event->data->object),
                'payment_intent.canceled' => $this->webhookHandler->handlePaymentIntentCanceled($event->data->object),
                default => null,
            };

            return $this->createApiResponse(['received' => true]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'error' => 'Webhook error',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function formatValidationErrors($errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }
        return $formatted;
    }
}
