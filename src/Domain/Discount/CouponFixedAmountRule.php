<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;

/**
 * "€10 off": a fixed net amount, never more than the items total.
 */
final class CouponFixedAmountRule extends AbstractCouponRule
{
    protected function couponType(): CouponType
    {
        return CouponType::Fixed;
    }

    protected function discountFor(Coupon $coupon, Money $itemsNet): Money
    {
        return $coupon->amount ?? throw new \LogicException('Fixed coupon without amount.');
    }
}
