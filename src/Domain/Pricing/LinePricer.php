<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Money\Money;

/**
 * net = unit net × quantity − discount; VAT = net × rate, rounded half-up per line;
 * gross = net + VAT.
 */
final class LinePricer
{
    public function price(PriceLine $line, ?Money $discountNet = null): PricedLine
    {
        $before = $line->netBeforeDiscount();
        $discount = $discountNet ?? Money::zero($before->currency);

        if ($discount->isNegative() || $discount->isGreaterThan($before)) {
            throw new \InvalidArgumentException(sprintf('Discount %s on line "%s" must be between 0 and %s.', $discount->toDecimalString(), $line->lineId, $before->toDecimalString()));
        }

        $net = $before->minus($discount);
        $tax = $line->taxRate->taxOn($net);

        return new PricedLine($line->lineId, $line->unitNet, $line->quantity, $line->taxRate, $before, $discount, $net, $tax, $net->plus($tax));
    }
}
