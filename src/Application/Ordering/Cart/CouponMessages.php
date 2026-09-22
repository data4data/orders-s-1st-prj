<?php

declare(strict_types=1);

namespace App\Application\Ordering\Cart;

use App\Domain\Discount\CouponRejectionReason;

/**
 * Customer-facing text for a coupon that cannot be used.
 */
final class CouponMessages
{
    public static function for(CouponRejectionReason $reason): string
    {
        return match ($reason) {
            CouponRejectionReason::Inactive, CouponRejectionReason::CurrencyMismatch => 'This code is not valid.',
            CouponRejectionReason::NotYetValid => 'This code is not valid yet.',
            CouponRejectionReason::Expired => 'This code has expired.',
            CouponRejectionReason::UsageLimitReached => 'This code has been used up.',
            CouponRejectionReason::BelowMinimumOrder => 'Your order is below the minimum amount for this code.',
        };
    }
}
