<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

/**
 * Starts a new payment attempt for an order awaiting payment; answers the payment page URL.
 */
final readonly class RetryPayment
{
    public function __construct(public string $id)
    {
    }
}
