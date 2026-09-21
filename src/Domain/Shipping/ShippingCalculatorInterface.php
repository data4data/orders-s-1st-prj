<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * Computes the net shipping cost for one kind of shipping method (shipping_method.calculator).
 * Add a new kind by adding a class; the registry picks it up by tag (decision #17).
 */
interface ShippingCalculatorInterface
{
    /** Matches shipping_method.calculator, e.g. "flat". */
    public function code(): string;

    /**
     * @param array<string, mixed> $config shipping_method.config (amounts in cents)
     *
     * @throws ShippingNotAvailableException
     * @throws InvalidShippingConfigException
     */
    public function calculate(Shipment $shipment, array $config): Money;
}
