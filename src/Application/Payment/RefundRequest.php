<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Domain\Money\Money;

final readonly class RefundRequest
{
    public function __construct(
        public string $externalReference,
        public Money $amount,
        public string $reason,
    ) {
    }
}
