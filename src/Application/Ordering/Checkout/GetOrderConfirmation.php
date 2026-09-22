<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

/**
 * Query: an order just placed, for its owner or the guest session that placed it.
 */
final readonly class GetOrderConfirmation
{
    public function __construct(public string $id)
    {
    }
}
