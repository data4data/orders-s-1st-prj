<?php

declare(strict_types=1);

namespace App\Domain\Discount;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
