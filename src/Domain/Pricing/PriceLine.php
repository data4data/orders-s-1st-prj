<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;
use App\Domain\Shared\Quantity;
use App\Domain\Tax\TaxRate;

/**
 * Input for pricing one order line: net unit price (as entered in the catalog), quantity
 * and the VAT rate that applies.
 */
final readonly class PriceLine
{
    public function __construct(
        public string $lineId,
        public Money $unitNet,
        public Quantity $quantity,
        public TaxRate $taxRate,
    ) {
        if ($unitNet->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Line "%s" has a negative unit price.', $lineId));
        }
    }

    public function netBeforeDiscount(): Money
    {
        return $this->unitNet->multipliedBy($this->quantity->value);
    }
}
