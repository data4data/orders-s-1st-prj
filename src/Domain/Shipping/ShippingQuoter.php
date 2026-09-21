<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Money\Money;

/**
 * Prices a shipping method for a shipment: checks the destination, then asks the method's
 * calculator (found by code among all registered calculators).
 */
final readonly class ShippingQuoter
{
    /** @var array<string, ShippingCalculatorInterface> */
    private array $calculators;

    /**
     * @param iterable<ShippingCalculatorInterface> $calculators
     */
    public function __construct(iterable $calculators)
    {
        $byCode = [];
        foreach ($calculators as $calculator) {
            $byCode[$calculator->code()] = $calculator;
        }
        $this->calculators = $byCode;
    }

    /**
     * @throws ShippingNotAvailableException
     * @throws InvalidShippingConfigException
     */
    public function quote(ShippingMethodSpec $method, Shipment $shipment): Money
    {
        if (!$method->shipsTo($shipment->destinationCountryCode)) {
            throw ShippingNotAvailableException::toCountry($method->code, $shipment->destinationCountryCode);
        }

        $calculator = $this->calculators[$method->calculatorCode]
            ?? throw InvalidShippingConfigException::unknownCalculator($method->calculatorCode);

        return $calculator->calculate($shipment, $method->config);
    }
}
