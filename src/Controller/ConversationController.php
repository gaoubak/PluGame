<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User;
use App\Form\ConversationType;
use App\Repository\ConversationRepository;
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
use App\Repository\MessageRepository;
use App\Service\MessageService;

#[Route('/api/conversations')]
class ConversationController extends AbstractFOSRestController
{
    use ApiResponseTrait;
    use FormHandlerTrait;

    public function __construct(
        private readonly ConversationRepository $repo,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly Security $security,
        private readonly MessageRepository $messages,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/', name: 'conversation_list', methods: ['GET'])]
    public function list(): Response
    {
        $items = $this->repo->findAll();
        $data  = $this->serializer->normalize($items, null, ['groups' => ['conversation:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'conversation_get', methods: ['GET'])]
    public function getOne(Conversation $conversation): Response
    {
        $data = $this->serializer->normalize($conversation, null, ['groups' => ['conversation:read']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/me', name: 'conversation_me', methods: ['GET'], priority: 10)]
    public function getMyConversations(): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Assuming Conversation has athlete and creator relations
        $myConversations = $this->repo->findByUser($user);

        $data = $this->serializer->normalize($myConversations, null, ['groups' => ['conversation:read']]);
        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/create', name: 'conversation_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->createApiResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $conversation = new Conversation();

        $form = $this->createForm(ConversationType::class, $conversation);
        $this->handleForm($request, $form);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->createApiResponse([
                'code' => 400, 'message' => 'Validation Failed', 'errors' => $form->getErrors(true, false)
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($conversation);
        $this->em->flush();

        $data = $this->serializer->normalize($conversation, null, ['groups' => ['conversation:write']]);
        return $this->createApiResponse($data, Response::HTTP_CREATED);
    }

    #[Route('/update/{id}', name: 'conversation_update', methods: ['PUT','PATCH'])]
    public function update(Request $request, Conversation $conversation): Response
    {
        $form = $this->formFactory->create(ConversationType::class, $conversation, [
            'method' => $request->getMethod(),
        ]);
        $this->handleForm($request, $form);

        if (!$form->isValid()) {
            return $this->createApiResponse(['errors' => (string)$form->getErrors(true, false)], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        $data = $this->serializer->normalize($conversation, null, ['groups' => ['conversation:write']]);
        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}/send-message', name: 'conversation_send_message', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{26,36}'])]
    public function sendMessage(string $id, Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Manually load by id (works for UUID/ULID/string ids)
        $conversation = $this->repo->find($id);
        if (!$conversation) {
            return $this->json(['message' => 'Conversation not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $content = (string) ($payload['content'] ?? '');

        // Use the service that actually has `send()` (see #2 below)
        $message = $this->messageService->send($user, $conversation, $content);

        return $this->json([
            'id'           => (string) $message->getId(),
            'conversation' => (string) $conversation->getId(),
            'sender'       => [
                'id'         => (string) $user->getId(),
                'identifier' => $user->getUserIdentifier(),
            ],
            'content'   => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }


    #[Route('/delete/{id}', name: 'conversation_delete', methods: ['DELETE'])]
    public function delete(Conversation $conversation): Response
    {
        $this->em->remove($conversation);
        $this->em->flush();
        return $this->renderDeletedResponse('Conversation deleted successfully');
    }
}
