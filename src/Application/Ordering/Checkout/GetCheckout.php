<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

/**
 * Query: everything the checkout wizard needs (cart, customer and address book, countries, shipping).
 */
final readonly class GetCheckout
{
    public function __construct(public ?string $country = null)
    {
    }
}
