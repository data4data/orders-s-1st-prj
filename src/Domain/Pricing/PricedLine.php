<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;
use App\Domain\Shared\Quantity;
use App\Domain\Tax\TaxRate;

/**
 * A fully priced line: exactly what order_item snapshots at checkout.
 */
final readonly class PricedLine
{
    public function __construct(
        public string $lineId,
        public Money $unitNet,
        public Quantity $quantity,
        public TaxRate $taxRate,
        public Money $netBeforeDiscount,
        public Money $discountNet,
        public Money $net,
        public Money $tax,
        public Money $gross,
    ) {
    }
}
