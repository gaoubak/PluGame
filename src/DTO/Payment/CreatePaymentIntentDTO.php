<?php

namespace App\DTO\Payment;

use App\DTO\AbstractRequestDTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating a payment intent
 *
 * Usage:
 * ```php
 * $dto = CreatePaymentIntentDTO::fromRequest($request, $validator);
 * // Automatically validated, throws ValidationFailedException on error
 * ```
 */
class CreatePaymentIntentDTO extends AbstractRequestDTO
{
    #[Assert\NotBlank(message: 'Amount is required', groups: ['create'])]
    #[Assert\Positive(message: 'Amount must be positive')]
    #[Assert\LessThan(100000000, message: 'Amount too large')]
    public int $amountCents;

    #[Assert\Uuid(message: 'Invalid booking ID format')]
    public ?string $bookingId = null;

    #[Assert\Type('bool')]
    public bool $useWallet = false;

    #[Assert\Type('bool')]
    public bool $isDeposit = false;

    /**
     * Validate that amount is at least $1.00 (100 cents)
     */
    #[Assert\GreaterThanOrEqual(100, message: 'Minimum amount is $1.00 (100 cents)')]
    public function getMinimumAmount(): int
    {
        return $this->amountCents;
    }
}
