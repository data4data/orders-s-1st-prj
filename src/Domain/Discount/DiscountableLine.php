<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;

final readonly class DiscountableLine
{
    public function __construct(
        public string $lineId,
        public Money $net,
    ) {
    }
}
