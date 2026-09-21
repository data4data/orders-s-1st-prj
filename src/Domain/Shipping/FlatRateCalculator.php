<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * Same price for every order. Config: {"amount": 695}.
 */
final class FlatRateCalculator extends AbstractShippingCalculator
{
    public function code(): string
    {
        return 'flat';
    }

    public function calculate(Shipment $shipment, array $config): Money
    {
        return $this->amount($config, 'amount', $shipment);
    }
}
