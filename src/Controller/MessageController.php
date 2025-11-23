<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\ConversationRepository;
use App\Service\MessageService;
use App\Traits\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/messages')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessageController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageService $messageService,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * List all messages (paginated)
     */
    #[Route('/', name: 'message_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $messages = $this->messageRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        $data = $this->serializer->normalize($messages, null, [
            'groups' => ['get_message', 'message:read']
        ]);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Get messages from a specific conversation
     */
    #[Route('/conversation/{id}', name: 'message_by_conversation', methods: ['GET'])]
    public function byConversation(
        Conversation $conversation,
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Security check: verify user is in conversation
        if (!$this->isUserInConversation($user, $conversation)) {
            return $this->forbiddenResponse('You are not part of this conversation');
        }

        // Pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));
        $offset = ($page - 1) * $limit;

        // Get total count
        $totalMessages = $this->messageRepository->countByConversation($conversation);

        // Get paginated messages
        $messages = $this->messageRepository->findByConversationPaginated($conversation, $limit, $offset);

        $data = $this->serializer->normalize($messages, null, [
            'groups' => ['get_message', 'message:read']
        ]);

        return $this->json([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalMessages,
                'totalPages' => (int) ceil($totalMessages / $limit),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get a single message
     */
    #[Route('/{id}', name: 'message_get', methods: ['GET'])]
    public function getOne(
        Message $message,
        #[CurrentUser] User $user
    ): JsonResponse {
        $conversation = $message->getConversation();

        if ($conversation && !$this->isUserInConversation($user, $conversation)) {
            return $this->forbiddenResponse('You cannot access this message');
        }

        $data = $this->serializer->normalize($message, null, [
            'groups' => ['get_message', 'message:read']
        ]);

        return $this->createApiResponse($data, Response::HTTP_OK);
    }

    /**
     * Send a new message in a conversation
     * Delegates to MessageService for validation, authorization, and Mercure publishing
     */
    #[Route('/send', name: 'message_create', methods: ['POST'])]
    public function send(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];

        $conversationId = $payload['conversationId'] ?? null;
        $content = (string) ($payload['content'] ?? '');

        // Find conversation
        if (!$conversationId) {
            return $this->errorResponse(
                'conversationId is required',
                Response::HTTP_BAD_REQUEST
            );
        }

        $conversation = $this->conversationRepository->find($conversationId);

        if (!$conversation) {
            return $this->notFoundResponse('Conversation not found');
        }

        try {
            // MessageService handles:
            // - Content validation
            // - Authorization check (user in conversation)
            // - Message creation & persistence
            // - Mercure publishing
            $message = $this->messageService->send($user, $conversation, $content);

            return $this->createdResponse([
                'id' => (string) $message->getId(),
                'conversationId' => (string) $conversation->getId(),
                'senderId' => (string) $user->getId(),
                'senderUsername' => $user->getUserIdentifier(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(\DATE_ATOM),
            ], 'Message sent successfully');
        } catch (BadRequestHttpException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        } catch (AccessDeniedHttpException $e) {
            return $this->forbiddenResponse($e->getMessage());
        }
    }

    /**
     * Delete a message (only by sender)
     */
    #[Route('/{id}', name: 'message_delete', methods: ['DELETE'])]
    public function delete(
        Message $message,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Security: Only the sender can delete their message
        if ($message->getSender()?->getId() !== $user->getId()) {
            return $this->forbiddenResponse('You can only delete your own messages');
        }

        $this->em->remove($message);
        $this->em->flush();

        return $this->renderDeletedResponse('Message deleted successfully');
    }

    /**
     * Helper: Check if user is participant in conversation
     * Based on your Conversation entity structure (athlete/creator)
     */
    private function isUserInConversation(User $user, Conversation $conversation): bool
    {
        $athleteId = $conversation->getAthlete()?->getId();
        $creatorId = $conversation->getCreator()?->getId();

        return $user->getId() === $athleteId || $user->getId() === $creatorId;
    }
}
