<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MessageService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * Create a message, persist it, and publish a Mercure update.
     *
     * @throws BadRequestHttpException      When content is invalid
     * @throws AccessDeniedHttpException    When sender not in conversation
     */
    public function send(User $sender, Conversation $conversation, string $content): Message
    {
        // 1) Validate content
        $violations = $this->validator->validate($content, [
            new Assert\NotBlank(message: 'Message content cannot be empty'),
            new Assert\Type('string'),
            new Assert\Length(
                min: 1,
                max: 2000,
                minMessage: 'Message must be at least {{ limit }} character long',
                maxMessage: 'Message cannot be longer than {{ limit }} characters'
            ),
        ]);

        if (\count($violations) > 0) {
            $errorMessage = '';
            foreach ($violations as $violation) {
                $errorMessage .= $violation->getMessage() . ' ';
            }
            throw new BadRequestHttpException(trim($errorMessage));
        }

        // 2) Authorization: sender must participate in conversation
        $isParticipant = \in_array(
            $sender->getId(),
            [
                $conversation->getAthlete()?->getId(),
                $conversation->getCreator()?->getId(),
            ],
            true
        );

        if (!$isParticipant) {
            throw new AccessDeniedHttpException('You are not a participant of this conversation.');
        }

        // 3) Create + persist message
        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($sender)
            ->setContent($content);

        $this->em->persist($message);
        $this->em->flush();

        // 4) Publish Mercure update
        try {
            $this->publishUpdate($conversation, $message, $sender);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            // Message is already saved in database
            error_log('Mercure publish failed: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * Build and publish a Mercure event for the given message.
     */
    private function publishUpdate(Conversation $conversation, Message $message, User $sender): void
    {
        // Use MercurePublisher for consistent topic format and payload structure
        // Parameters kept for backward compatibility but not used
        $this->mercurePublisher->publishMessageCreated($message);
    }
}
