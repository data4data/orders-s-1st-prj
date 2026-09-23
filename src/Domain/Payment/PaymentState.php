<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/**
 * Places of the payment state machine (architecture.md §4).
 */
enum PaymentState: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isOpen(): bool
    {
        return self::Pending === $this || self::Authorized === $this;
    }
}
