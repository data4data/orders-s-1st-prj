<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

/**
 * Turns the cart into an order: price, discount, VAT, stock check, `checkout` transition,
 * stock reservation, snapshots, order number and a payment session (architecture.html → Checkout flow).
 */
final readonly class PlaceOrder
{
    public function __construct(public CheckoutInput $input)
    {
    }
}
