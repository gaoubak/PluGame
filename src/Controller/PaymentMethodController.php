<?php

// src/Controller/PaymentMethodController.php

namespace App\Controller;

use App\Entity\User;
use App\Service\Stripe\StripeService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payment-methods')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PaymentMethodController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly StripeService $stripeService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * ✅ Create setup intent for adding card
     */
    #[Route('/setup-intent', name: 'create_setup_intent', methods: ['POST'])]
    public function createSetupIntent(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $setupIntent = $this->stripeService->createSetupIntent($user);
            $this->em->flush();

            return $this->createApiResponse([
                'clientSecret' => $setupIntent->client_secret,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to create setup intent: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ List payment methods
     */
    #[Route('', name: 'list_payment_methods', methods: ['GET'])]
    public function listPaymentMethods(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $customer = $this->stripeService->getOrCreateCustomer($user);
            $paymentMethods = $this->stripeService->listPaymentMethods($user);

            $data = array_map(function ($pm) use ($customer) {
                $card = $this->stripeService->formatCard($pm);
                $card['isDefault'] = $customer->invoice_settings->default_payment_method === $pm->id;
                return $card;
            }, $paymentMethods);
            $defaultPaymentMethodId = null;

            foreach ($data as $pm) {
                if ($pm['isDefault']) {
                    $defaultPaymentMethodId = $pm['id'];
                    break;
                }
            }
            $user->setStripePaymentMethods($defaultPaymentMethodId);
            $this->em->persist($user);
            $this->em->flush();

            return $this->createApiResponse($data);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to list payment methods: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Attach payment method
     */
    #[Route('/attach', name: 'attach_payment_method', methods: ['POST'])]
    public function attachPaymentMethod(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $paymentMethodId = $data['paymentMethodId'] ?? null;

        if (!$paymentMethodId) {
            return $this->createApiResponse([
                'message' => 'Payment method ID required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentMethod = $this->stripeService->attachPaymentMethod($user, $paymentMethodId);
            $this->em->flush();

            return $this->createApiResponse([
                'message' => 'Payment method attached',
                'paymentMethod' => $this->stripeService->formatCard($paymentMethod),
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to attach payment method: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Set default payment method
     */
    #[Route('/{id}/set-default', name: 'set_default_payment_method', methods: ['POST'])]
    public function setDefaultPaymentMethod(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        try {
            $this->stripeService->setDefaultPaymentMethod($user, $id);

            return $this->createApiResponse([
                'message' => 'Default payment method updated',
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to set default: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ✅ Delete payment method
     */
    #[Route('/{id}', name: 'delete_payment_method', methods: ['DELETE'])]
    public function deletePaymentMethod(string $id): JsonResponse
    {
        try {
            $this->stripeService->detachPaymentMethod($id);

            return $this->createApiResponse([
                'message' => 'Payment method removed',
            ]);
        } catch (\Exception $e) {
            return $this->createApiResponse([
                'message' => 'Failed to remove payment method: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
