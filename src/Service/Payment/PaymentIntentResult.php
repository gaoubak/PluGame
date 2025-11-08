<?php

namespace App\Service\Payment;

use App\Entity\Payment;

/**
 * Result object from payment intent creation
 */
readonly class PaymentIntentResult
{
    public function __construct(
        public Payment $payment,
        public ?string $paymentIntentId,
        public ?string $clientSecret,
        public int $walletUsed,
        public int $cardCharge,
        public bool $isFullyPaidByWallet,
    ) {
    }

    public function toArray(): array
    {
        $result = [
            'paymentId' => $this->payment->getId(),
            'walletUsed' => $this->walletUsed / 100,
            'cardCharge' => $this->cardCharge / 100,
            'fullyPaidByWallet' => $this->isFullyPaidByWallet,
        ];

        if ($this->paymentIntentId) {
            $result['paymentIntentId'] = $this->paymentIntentId;
            $result['clientSecret'] = $this->clientSecret;
        }

        return $result;
    }
}
