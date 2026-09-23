<?php

declare(strict_types=1);

namespace App\Application\Ordering\Cart;

/**
 * Removes a line from the cart.
 */
final readonly class RemoveCartLine
{
    public function __construct(public string $variantId)
    {
    }
}
