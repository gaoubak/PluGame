<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for User entity
 *
 * Controls access to user profiles and personal data
 */
class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';
    public const VIEW_PRIVATE = 'USER_VIEW_PRIVATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof User && in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::VIEW_PRIVATE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Admin can do everything
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($targetUser, $user),
            self::EDIT => $this->canEdit($targetUser, $user),
            self::DELETE => $this->canDelete($targetUser, $user),
            self::VIEW_PRIVATE => $this->canViewPrivate($targetUser, $user),
            default => false,
        };
    }

    private function canView(User $targetUser, User $user): bool
    {
        // Can view public profiles if user is active
        if ($targetUser->isActive()) {
            return true;
        }

        // Can view own profile even if inactive
        return $targetUser->getId() === $user->getId();
    }

    private function canEdit(User $targetUser, User $user): bool
    {
        // Can only edit own profile
        return $targetUser->getId() === $user->getId();
    }

    private function canDelete(User $targetUser, User $user): bool
    {
        // Can only delete own account
        return $targetUser->getId() === $user->getId();
    }

    private function canViewPrivate(User $targetUser, User $user): bool
    {
        // Can view private data (email, phone, etc.) only for own profile
        return $targetUser->getId() === $user->getId();
    }
}
