<?php

declare(strict_types=1);

namespace App\Domain\Discount;

/**
 * Why a coupon cannot be used. The UI turns each case into a short message under the field.
 */
enum CouponRejectionReason: string
{
    case Inactive = 'inactive';
    case NotYetValid = 'not_yet_valid';
    case Expired = 'expired';
    case UsageLimitReached = 'usage_limit_reached';
    case BelowMinimumOrder = 'below_minimum_order';
    case CurrencyMismatch = 'currency_mismatch';
}
