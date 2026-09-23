<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * Free from a minimum order value, otherwise a fixed price.
 * Config: {"amount": 695, "threshold": 10000}.
 */
final class FreeOverThresholdCalculator extends AbstractShippingCalculator
{
    public function code(): string
    {
        return 'free_over_threshold';
    }

    public function calculate(Shipment $shipment, array $config): Money
    {
        $threshold = $this->amount($config, 'threshold', $shipment);

        return $shipment->orderValue->isGreaterThanOrEqualTo($threshold)
            ? Money::zero($shipment->orderValue->currency)
            : $this->amount($config, 'amount', $shipment);
    }
}
