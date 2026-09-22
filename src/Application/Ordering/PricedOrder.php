<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Domain\Discount\CouponRejectionReason;
use App\Domain\Pricing\OrderTotals;
use App\Domain\Pricing\PricedLine;

/**
 * Result of OrderPricer: priced lines (keyed by variant public id), totals and what went wrong.
 */
final readonly class PricedOrder
{
    /**
     * @param array<string, PricedLine> $lines
     * @param list<string>              $unavailable variant public ids (or SKUs) that can no longer be bought
     */
    public function __construct(
        public array $lines,
        public OrderTotals $totals,
        public ?PricedLine $shipping,
        public ?CouponRejectionReason $couponError,
        public ?string $shippingError,
        public array $unavailable,
        public int $weightGrams,
        public int $itemsGrossAfterDiscount,
    ) {
    }
}
