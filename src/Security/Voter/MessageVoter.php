<?php

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for Message entity
 *
 * Ensures users can only access messages in conversations they're part of
 */
class MessageVoter extends Voter
{
    public const VIEW = 'MESSAGE_VIEW';
    public const CREATE = 'MESSAGE_CREATE';
    public const DELETE = 'MESSAGE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Message && in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::DELETE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Message $message */
        $message = $subject;

        // Admin can do everything
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($message, $user),
            self::CREATE => $this->canCreate($message, $user),
            self::DELETE => $this->canDelete($message, $user),
            default => false,
        };
    }

    private function canView(Message $message, User $user): bool
    {
        $conversation = $message->getConversation();

        if (!$conversation) {
            return false;
        }

        // Can view if you're part of the conversation
        return $conversation->getAthlete()?->getId() === $user->getId()
            || $conversation->getCreator()?->getId() === $user->getId();
    }

    private function canCreate(Message $message, User $user): bool
    {
        $conversation = $message->getConversation();

        if (!$conversation) {
            return false;
        }

        // Can create if you're part of the conversation
        return $conversation->getAthlete()?->getId() === $user->getId()
            || $conversation->getCreator()?->getId() === $user->getId();
    }

    private function canDelete(Message $message, User $user): bool
    {
        // Can delete own messages
        return $message->getSender()?->getId() === $user->getId();
    }
}
