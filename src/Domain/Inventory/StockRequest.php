<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Shared\Quantity;

final readonly class StockRequest
{
    public function __construct(
        public string $sku,
        public StockLevel $stock,
        public Quantity $quantity,
    ) {
    }
}
