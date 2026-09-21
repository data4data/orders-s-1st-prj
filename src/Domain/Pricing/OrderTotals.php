<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;

/**
 * Order totals as stored on the order: items (before discount), discount, shipping, and the
 * net / VAT / gross grand totals. VAT is the sum of the per-line VAT (lines are rounded first).
 */
final readonly class OrderTotals
{
    private function __construct(
        public Money $itemsNet,
        public Money $discountNet,
        public Money $shippingNet,
        public Money $totalNet,
        public Money $totalTax,
        public Money $totalGross,
    ) {
    }

    /**
     * @param list<PricedLine> $items
     * @param PricedLine|null  $shipping shipping priced as a line with its own VAT rate
     */
    public static function calculate(string $currency, array $items, ?PricedLine $shipping = null): self
    {
        $itemsNet = $discount = $tax = Money::zero($currency);
        foreach ($items as $line) {
            $itemsNet = $itemsNet->plus($line->netBeforeDiscount);
            $discount = $discount->plus($line->discountNet);
            $tax = $tax->plus($line->tax);
        }

        $shippingNet = $shipping->net ?? Money::zero($currency);
        $tax = $tax->plus($shipping->tax ?? Money::zero($currency));
        $totalNet = $itemsNet->minus($discount)->plus($shippingNet);

        return new self($itemsNet, $discount, $shippingNet, $totalNet, $tax, $totalNet->plus($tax));
    }
}
