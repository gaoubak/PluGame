<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use App\Entity\User;
use App\Form\AvailabilitySlotType;
use App\Repository\AvailabilitySlotRepository;
use App\Traits\ApiResponseTrait;
use App\Traits\FormHandlerTrait;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\UserRepository;

#[Route('/api/slots')]
class AvailabilitySlotController extends AbstractFOSRestController
{
    use ApiResponseTrait;
    use FormHandlerTrait;

    public function __construct(
        private readonly AvailabilitySlotRepository $repo,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly Security $security,
        private readonly AvailabilitySlotRepository $slots,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/', name: 'slot_list', methods: ['GET'])]
    public function list(): Response
    {
        $items = $this->repo->findAll();
        $data  = $this->serializer->normalize($items, null, ['groups' => ['slot:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/mine', name: 'slot_my_list', methods: ['GET'])]
    public function mySlots(): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $items = $this->repo->findBy(['creator' => $user]);
        $data  = $this->serializer->normalize($items, null, ['groups' => ['slot:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'slot_get', methods: ['GET'])]
    public function getOne(AvailabilitySlot $slot): Response
    {
        $data = $this->serializer->normalize($slot, null, ['groups' => ['slot:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }



    #[Route('/create', name: 'slot_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // The entity needs creator in constructor:
        $slot = new AvailabilitySlot($user, new \DateTimeImmutable(), new \DateTimeImmutable());

        $form = $this->createForm(AvailabilitySlotType::class, $slot);
        $this->handleForm($request, $form);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->createApiResponse([
                'code' => 400,'message' => 'Validation Failed','errors' => $form->getErrors(true, false)
            ], Response::HTTP_BAD_REQUEST);
        }

        // enforce owner
        $slot->setCreator($user);

        if ($this->slots->existsOverlap($user, $slot->getStartTime(), $slot->getEndTime())) {
            return $this->createApiResponse([
                'code' => 409,
                'message' => 'This slot overlaps an existing one.',
            ], Response::HTTP_CONFLICT);
        }

        $this->em->persist($slot);
        $this->em->flush();

        $data = $this->serializer->normalize($slot, null, ['groups' => ['slot:write']]);
        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    #[Route('/bulk', name: 'slot_bulk_create', methods: ['POST'])]
    public function bulkCreate(Request $request, \App\Service\AvailabilitySlotBulkService $bulk): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $items   = $payload['slots'] ?? null;

        if (!is_array($items)) {
            return $this->createApiResponse([
                'code' => 400,
                'message' => 'Body must contain "slots": [{startTime, endTime}, ...]',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $bulk->createMany($user, $items);

        return $this->createApiResponse([
            'created' => $result['created'],
            'errors'  => $result['errors'],
        ], empty($result['created']) ? Response::HTTP_BAD_REQUEST : Response::HTTP_CREATED);
    }


    #[Route('/update/{id}', name: 'slot_update', methods: ['PUT','PATCH'])]
    public function update(Request $request, AvailabilitySlot $slot): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user || $slot->getCreator()->getId() !== $user->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $form = $this->formFactory->create(AvailabilitySlotType::class, $slot, [
            'method' => $request->getMethod(),
        ]);
        $this->handleForm($request, $form);

        if (!$form->isValid()) {
            return $this->createApiResponse(['errors' => (string)$form->getErrors(true, false)], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();
        $data = $this->serializer->normalize($slot, null, ['groups' => ['slot:write']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'slot_delete', methods: ['DELETE'])]
    public function delete(AvailabilitySlot $slot): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$user || $slot->getCreator()->getId() !== $user->getId()) {
            return $this->createApiResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($slot);
        $this->em->flush();
        return $this->renderDeletedResponse('Availability slot deleted successfully');
    }

    #[Route('/user/{userId}', name: 'slot_by_user', methods: ['GET'])]
    public function getSlotsByUser(string $userId, UserRepository $userRepo): Response
    {
        $user = $userRepo->find($userId);
        if (!$user) {
            return $this->createApiResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $slots = $this->repo->findBy(['creator' => $user]);
        $data  = $this->serializer->normalize($slots, null, ['groups' => ['slot:read']]);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }
}
