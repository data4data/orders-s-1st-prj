<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

/**
 * Creates (id null) or changes a coupon.
 */
final readonly class SaveCoupon
{
    public function __construct(public ?int $id, public CouponInput $input)
    {
    }
}
