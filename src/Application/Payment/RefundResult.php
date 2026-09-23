<?php

declare(strict_types=1);

namespace App\Application\Payment;

final readonly class RefundResult
{
    public function __construct(
        public bool $succeeded,
        public string $externalReference,
    ) {
    }
}
