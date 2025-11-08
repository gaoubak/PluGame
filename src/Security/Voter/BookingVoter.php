<?php

namespace App\Security\Voter;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for Booking entity
 *
 * Protects against IDOR vulnerabilities by ensuring users can only
 * access bookings they're involved in (as athlete or creator)
 */
class BookingVoter extends Voter
{
    public const VIEW = 'BOOKING_VIEW';
    public const EDIT = 'BOOKING_EDIT';
    public const DELETE = 'BOOKING_DELETE';
    public const ACCEPT = 'BOOKING_ACCEPT';
    public const DECLINE = 'BOOKING_DECLINE';
    public const CANCEL = 'BOOKING_CANCEL';
    public const COMPLETE = 'BOOKING_COMPLETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Booking && in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::ACCEPT,
            self::DECLINE,
            self::CANCEL,
            self::COMPLETE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Booking $booking */
        $booking = $subject;

        // Admin can do everything
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($booking, $user),
            self::EDIT => $this->canEdit($booking, $user),
            self::DELETE => $this->canDelete($booking, $user),
            self::ACCEPT => $this->canAccept($booking, $user),
            self::DECLINE => $this->canDecline($booking, $user),
            self::CANCEL => $this->canCancel($booking, $user),
            self::COMPLETE => $this->canComplete($booking, $user),
            default => false,
        };
    }

    private function canView(Booking $booking, User $user): bool
    {
        // Can view if you're the athlete or creator
        return $booking->getAthlete()?->getId() === $user->getId()
            || $booking->getCreator()?->getId() === $user->getId();
    }

    private function canEdit(Booking $booking, User $user): bool
    {
        // Only athlete can edit (modify dates, etc.) before accepted
        return $booking->getAthlete()?->getId() === $user->getId()
            && $booking->getStatus() === Booking::STATUS_PENDING;
    }

    private function canDelete(Booking $booking, User $user): bool
    {
        // Only athlete can delete, and only if still pending
        return $booking->getAthlete()?->getId() === $user->getId()
            && $booking->getStatus() === Booking::STATUS_PENDING;
    }

    private function canAccept(Booking $booking, User $user): bool
    {
        // Only creator can accept, and only if pending
        return $booking->getCreator()?->getId() === $user->getId()
            && $booking->getStatus() === Booking::STATUS_PENDING;
    }

    private function canDecline(Booking $booking, User $user): bool
    {
        // Only creator can decline, and only if pending
        return $booking->getCreator()?->getId() === $user->getId()
            && $booking->getStatus() === Booking::STATUS_PENDING;
    }

    private function canCancel(Booking $booking, User $user): bool
    {
        // Both athlete and creator can cancel
        $isParticipant = $booking->getAthlete()?->getId() === $user->getId()
            || $booking->getCreator()?->getId() === $user->getId();

        return $isParticipant && $booking->canBeCancelled();
    }

    private function canComplete(Booking $booking, User $user): bool
    {
        // Only creator can mark as complete
        return $booking->getCreator()?->getId() === $user->getId()
            && in_array($booking->getStatus(), [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS], true);
    }
}
