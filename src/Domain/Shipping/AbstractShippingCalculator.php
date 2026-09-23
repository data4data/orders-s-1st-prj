<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * Shared helpers for reading calculator config safely.
 */
abstract class AbstractShippingCalculator implements ShippingCalculatorInterface
{
    /**
     * @param array<string, mixed> $config
     */
    protected function amount(array $config, string $key, Shipment $shipment): Money
    {
        return Money::of($this->nonNegativeInt($config, $key), $shipment->orderValue->currency);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function nonNegativeInt(array $config, string $key): int
    {
        $value = $config[$key] ?? null;
        if (!\is_int($value) || $value < 0) {
            throw InvalidShippingConfigException::missing($this->code(), $key);
        }

        return $value;
    }
}
