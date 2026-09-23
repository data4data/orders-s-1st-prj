<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

/**
 * The customer (or the guest session that placed it) cancels an order that is not paid yet.
 */
final readonly class CancelMyOrder
{
    public function __construct(public string $id)
    {
    }
}
