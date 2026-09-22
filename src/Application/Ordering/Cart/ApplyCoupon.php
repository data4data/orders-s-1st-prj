<?php

declare(strict_types=1);

namespace App\Application\Ordering\Cart;

/**
 * Applies a coupon code to the cart.
 */
final readonly class ApplyCoupon
{
    public function __construct(public string $code)
    {
    }
}
