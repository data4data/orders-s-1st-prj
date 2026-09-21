<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * What a shipping calculator needs: the order value (gross, after discount), the total
 * weight and the destination.
 */
final readonly class Shipment
{
    public string $destinationCountryCode;

    public function __construct(
        public Money $orderValue,
        public int $weightGrams,
        string $destinationCountryCode,
    ) {
        if ($weightGrams < 0) {
            throw new \InvalidArgumentException('Shipment weight cannot be negative.');
        }
        $this->destinationCountryCode = strtoupper($destinationCountryCode);
    }
}
