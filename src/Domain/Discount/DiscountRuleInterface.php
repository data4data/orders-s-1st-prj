<?php

declare(strict_types=1);

namespace App\Domain\Discount;

/**
 * One kind of discount. Add a new kind (e.g. an automatic category promotion) by adding a
 * class; it is picked up by tag, no existing code changes (decision #16).
 */
interface DiscountRuleInterface
{
    public function supports(DiscountContext $context): bool;

    /** @throws CouponNotApplicableException */
    public function apply(DiscountContext $context): DiscountResult;
}
