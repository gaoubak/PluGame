<?php

namespace App\Security\Voter;

use App\Entity\Payment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for Payment entity
 *
 * Protects sensitive payment information from unauthorized access
 */
class PaymentVoter extends Voter
{
    public const VIEW = 'PAYMENT_VIEW';
    public const CREATE = 'PAYMENT_CREATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Payment && in_array($attribute, [
            self::VIEW,
            self::CREATE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Payment $payment */
        $payment = $subject;

        // Admin can view all payments
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($payment, $user),
            self::CREATE => $this->canCreate($payment, $user),
            default => false,
        };
    }

    private function canView(Payment $payment, User $user): bool
    {
        // Can view own payments
        if ($payment->getUser()?->getId() === $user->getId()) {
            return true;
        }

        // Creator can view payments for their bookings
        $booking = $payment->getBooking();
        if ($booking && $booking->getCreator()?->getId() === $user->getId()) {
            return true;
        }

        return false;
    }

    private function canCreate(Payment $payment, User $user): bool
    {
        // Can only create payments for yourself
        return $payment->getUser()?->getId() === $user->getId();
    }
}
