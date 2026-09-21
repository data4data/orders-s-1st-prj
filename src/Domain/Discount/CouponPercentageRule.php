<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;

/**
 * "10% off": the percentage of the items net total, rounded half-up.
 */
final class CouponPercentageRule extends AbstractCouponRule
{
    protected function couponType(): CouponType
    {
        return CouponType::Percentage;
    }

    protected function discountFor(Coupon $coupon, Money $itemsNet): Money
    {
        return ($coupon->percent ?? throw new \LogicException('Percentage coupon without percentage.'))->applyTo($itemsNet);
    }
}
