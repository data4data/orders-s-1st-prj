<?php

declare(strict_types=1);

namespace App\Application\Ordering\Cart;

/**
 * Sets the quantity of a cart line.
 */
final readonly class UpdateCartLine
{
    public function __construct(public string $variantId, public int $quantity)
    {
    }
}
