<?php

declare(strict_types=1);

namespace App\Domain\Discount;

final class CouponNotApplicableException extends \DomainException
{
    public function __construct(
        public readonly string $couponCode,
        public readonly CouponRejectionReason $reason,
    ) {
        parent::__construct(sprintf('Coupon "%s" cannot be used: %s.', $couponCode, str_replace('_', ' ', $reason->value)));
    }
}
