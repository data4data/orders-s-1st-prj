<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

final readonly class StockShortage
{
    public function __construct(
        public string $sku,
        public int $requested,
        public int $available,
    ) {
    }
}
