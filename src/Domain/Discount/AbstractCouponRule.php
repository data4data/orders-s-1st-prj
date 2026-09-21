<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;

/**
 * Shared coupon behaviour: check the coupon may be used, cap the discount at the items total,
 * and split it over the lines in proportion to their net amounts.
 */
abstract class AbstractCouponRule implements DiscountRuleInterface
{
    public function supports(DiscountContext $context): bool
    {
        return $context->coupon?->type === $this->couponType();
    }

    public function apply(DiscountContext $context): DiscountResult
    {
        $coupon = $context->coupon ?? throw new \LogicException('apply() called without a coupon.');
        $itemsNet = $context->itemsNet();
        $coupon->assertUsable($itemsNet, $context->now);

        $total = $this->discountFor($coupon, $itemsNet)->min($itemsNet);
        if ([] === $context->lines) {
            return new DiscountResult($total, [], [$coupon->code]);
        }

        $weights = [];
        foreach ($context->lines as $line) {
            $weights[$line->lineId] = $line->net->amount;
        }

        return new DiscountResult($total, $total->allocate($weights), [$coupon->code]);
    }

    abstract protected function couponType(): CouponType;

    abstract protected function discountFor(Coupon $coupon, Money $itemsNet): Money;
}
