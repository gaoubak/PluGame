<?php

namespace App\Controller;

use App\Entity\ServiceOffering;
use App\Entity\User;
use App\Form\ServiceOfferingType;
use App\Repository\ServiceOfferingRepository;
use App\Traits\ApiResponseTrait;
use App\Traits\FormHandlerTrait;
use App\Service\Stripe\StripeService;                 // <-- add
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api/services')]
class ServiceOfferingController extends AbstractFOSRestController
{
    use ApiResponseTrait;
    use FormHandlerTrait;

    public function __construct(
        private readonly ServiceOfferingRepository $services,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly Security $security,
        private readonly StripeService $stripeService        // <-- inject it
    ) {
    }

    #[Route('/', name: 'service_list', methods: ['GET'])]
    public function list(): Response
    {
        $items = $this->services->findAll();
        $data  = $this->serializer->normalize($items, null, ['groups' => ['service:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'service_get', methods: ['GET'])]
    public function getOne(ServiceOffering $service): Response
    {
        $data = $this->serializer->normalize($service, null, ['groups' => ['service:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/user/{userId}', name: 'service_by_user', methods: ['GET'])]
    public function getByUser(string $userId): Response
    {
        $userServices = $this->services->findBy(['creator' => $userId]);

        $data = $this->serializer->normalize($userServices, null, ['groups' => ['service:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }


    #[Route('/create', name: 'service_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $service = new ServiceOffering($user);

        $form = $this->createForm(ServiceOfferingType::class, $service);
        $this->handleForm($request, $form);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->createApiResponse([
                'code' => 400,
                'message' => 'Validation Failed',
                'errors' => $form->getErrors(true, false),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($service->isFeatured()) {
            $existingFeatured = $this->em->getRepository(ServiceOffering::class)
                ->findOneBy(['creator' => $user, 'featured' => true]);

            if ($existingFeatured) {
                return $this->createApiResponse([
                    'message' => 'You already have a featured service. Only one featured service is allowed.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $service->setCreator($user);
        $this->em->persist($service);
        $this->em->flush();

        // Create/refresh Stripe Product + Price (TOTAL after fee & tax)
        $this->stripeService->syncServicePrice($service, false); // buyerIsPlugPlus=false for catalog

        $data = $this->serializer->normalize($service, null, ['groups' => ['service:read']]);
        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    #[Route('/update/{id}', name: 'service_update', methods: ['PUT','PATCH'])]
    public function update(Request $request, ServiceOffering $service): Response
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$currentUser || $service->getCreator()->getId() !== $currentUser->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $form = $this->formFactory->create(ServiceOfferingType::class, $service, [
            'method' => $request->getMethod(),
            'current_user' => $currentUser,
        ]);
        $this->handleForm($request, $form);

        if (!$form->isValid()) {
            return $this->createApiResponse(['errors' => (string)$form->getErrors(true, false)], Response::HTTP_BAD_REQUEST);
        }

        if ($service->isFeatured()) {
            $existingFeatured = $this->em->getRepository(ServiceOffering::class)
                ->findOneBy(['creator' => $currentUser, 'featured' => true]);

            // Check that it's not the same service being updated
            if ($existingFeatured && $existingFeatured->getId() !== $service->getId()) {
                return $this->createApiResponse([
                    'message' => 'You already have a featured service. Only one featured service is allowed.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }


        $this->em->flush();

        // Re-sync Stripe price if base price/duration changed
        $this->stripeService->syncServicePrice($service, false);

        $data = $this->serializer->normalize($service, null, ['groups' => ['service:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'service_delete', methods: ['DELETE'])]
    public function delete(ServiceOffering $service): Response
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$currentUser || $service->getCreator()->getId() !== $currentUser->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($service);
        $this->em->flush();

        return $this->renderDeletedResponse('Service offering deleted successfully');
    }
}
