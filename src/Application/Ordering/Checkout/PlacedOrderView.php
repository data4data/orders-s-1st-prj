<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

final readonly class PlacedOrderView
{
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public string $redirectUrl,
    ) {
    }
}
