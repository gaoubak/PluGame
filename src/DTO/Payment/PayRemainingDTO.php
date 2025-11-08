<?php

namespace App\DTO\Payment;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for paying remaining amount
 */
class PayRemainingDTO
{
    #[Assert\Type('bool')]
    public bool $useWallet = false;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->useWallet = (bool) ($data['useWallet'] ?? false);

        return $dto;
    }
}
