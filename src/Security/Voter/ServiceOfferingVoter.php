<?php

namespace App\Security\Voter;

use App\Entity\ServiceOffering;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for ServiceOffering entity
 *
 * Controls who can create, edit, or delete services
 */
class ServiceOfferingVoter extends Voter
{
    public const VIEW = 'SERVICE_VIEW';
    public const CREATE = 'SERVICE_CREATE';
    public const EDIT = 'SERVICE_EDIT';
    public const DELETE = 'SERVICE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // CREATE doesn't need a subject
        if ($attribute === self::CREATE) {
            return true;
        }

        return $subject instanceof ServiceOffering && in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin can do everything
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => false,
        };
    }

    private function canView(?ServiceOffering $service, User $user): bool
    {
        // Public services can be viewed by anyone authenticated
        if ($service && $service->isActive()) {
            return true;
        }

        // Inactive services only visible to owner
        return $service && $service->getCreator()?->getId() === $user->getId();
    }

    private function canCreate(User $user): bool
    {
        // Only creators can create services
        return in_array('ROLE_CREATOR', $user->getRoles(), true)
            || $user->getCreatorProfile() !== null;
    }

    private function canEdit(?ServiceOffering $service, User $user): bool
    {
        // Only the creator who owns the service can edit
        return $service && $service->getCreator()?->getId() === $user->getId();
    }

    private function canDelete(?ServiceOffering $service, User $user): bool
    {
        // Only the creator who owns the service can delete
        return $service && $service->getCreator()?->getId() === $user->getId();
    }
}
