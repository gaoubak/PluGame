<?php

// src/Controller/PayoutController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PayoutMethod;
use App\Entity\Payment;
use App\Repository\PayoutMethodRepository;
use App\Repository\PaymentRepository;
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
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $em,
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
        $accountLast4 = $data['accountLast4'] ?? null;

        if (!$bankName || !$accountLast4) {
            return $this->createApiResponse([
                'message' => 'Bank name and account last 4 digits required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $method = new PayoutMethod();
            $method->setUser($user);
            $method->setType(PayoutMethod::TYPE_BANK_ACCOUNT);
            $method->setBankName($bankName);
            $method->setAccountLast4($accountLast4);

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
     */
    #[Route('/revenues', name: 'get_revenues', methods: ['GET'])]
    public function getRevenues(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        try {
            // Get completed payments where user is the creator (received money)
            $qb = $this->paymentRepository->createQueryBuilder('p');
            $qb->join('p.booking', 'b')
                ->join('b.creator', 'c')
                ->where('c.id = :userId')
                ->andWhere('p.status = :status')
                ->setParameter('userId', $user->getId())
                ->setParameter('status', Payment::STATUS_COMPLETED)
                ->orderBy('p.createdAt', 'DESC');

            $payments = $qb->getQuery()->getResult();

            $totalRevenue = array_reduce($payments, function ($sum, $payment) {
                return $sum + $payment->getAmountCents();
            }, 0);

            $revenueData = array_map(fn($payment) => [
                'id' => $payment->getId(),
                'amountCents' => $payment->getAmountCents(),
                'createdAt' => $payment->getCreatedAt()->format('c'),
                'booking' => [
                    'id' => $payment->getBooking()->getId(),
                    'service' => $payment->getBooking()->getService()?->getTitle(),
                    'athlete' => [
                        'id' => $payment->getBooking()->getAthlete()->getId(),
                        'username' => $payment->getBooking()->getAthlete()->getUsername(),
                    ],
                ],
            ], $payments);

            return $this->createApiResponse([
                'totalRevenueCents' => $totalRevenue,
                'formattedRevenue' => number_format($totalRevenue / 100, 2) . ' €',
                'totalTransactions' => count($payments),
                'revenues' => $revenueData,
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to get revenues: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
