<?php

declare(strict_types=1);

namespace App\Application\Ordering\Cart;

/**
 * Adds a pack size to the cart (or raises its quantity).
 */
final readonly class AddToCart
{
    public function __construct(public string $variantId, public int $quantity = 1)
    {
    }
}
