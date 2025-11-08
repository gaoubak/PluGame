<?php

namespace App\Service;

use App\Entity\ServiceOffering;

class PricingService
{
    /**
     * @return array{subtotal:int, fee:int, tax:int, total:int}
     */
    public function quote(
        ServiceOffering $service,
        int $bookedMinutes,
        bool $isPlugPlus,
        int $taxPercent = 0
    ): array {
        $duration = max(1, (int) $service->getDurationMin());
        $price    = max(0, (int) $service->getPriceCents());

        // base per-minute rate derived from service
        $ratePerMin = (int) \round($price / $duration);
        $subtotal   = $ratePerMin * max(0, $bookedMinutes);

        $feeRate = $isPlugPlus ? 0.05 : 0.15;
        $fee     = (int) \round($subtotal * $feeRate);

        $tax     = (int) \round(($subtotal + $fee) * max(0, $taxPercent) / 100);
        $total   = $subtotal + $fee + $tax;

        return ['subtotal' => $subtotal, 'fee' => $fee, 'tax' => $tax, 'total' => $total];
    }
}
