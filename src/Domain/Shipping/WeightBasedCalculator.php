<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * Price by weight bracket; the first bracket the shipment fits in wins.
 * Config: {"brackets": [{"up_to_grams": 5000, "amount": 695}, {"up_to_grams": null, "amount": 1995}]}
 * (null = no upper limit).
 */
final class WeightBasedCalculator extends AbstractShippingCalculator
{
    public function code(): string
    {
        return 'weight_based';
    }

    public function calculate(Shipment $shipment, array $config): Money
    {
        $brackets = $config['brackets'] ?? null;
        if (!\is_array($brackets) || [] === $brackets) {
            throw InvalidShippingConfigException::missing($this->code(), 'brackets');
        }

        foreach ($brackets as $bracket) {
            if (!\is_array($bracket)) {
                throw InvalidShippingConfigException::missing($this->code(), 'brackets');
            }
            $upTo = $bracket['up_to_grams'] ?? null;
            if (null === $upTo || (\is_int($upTo) && $shipment->weightGrams <= $upTo)) {
                /* @var array<string, mixed> $bracket */
                return $this->amount($bracket, 'amount', $shipment);
            }
        }

        throw ShippingNotAvailableException::forWeight($this->code(), $shipment->weightGrams);
    }
}
