<?php

declare(strict_types=1);

namespace App\Application\Payment;

/**
 * Gateway-neutral payment events; each maps to a payment workflow transition.
 */
enum PaymentEventType: string
{
    case Authorized = 'authorize';
    case Captured = 'capture';
    case Failed = 'fail';
    case Cancelled = 'cancel';
    case Refunded = 'refund';

    public function transition(): string
    {
        return $this->value;
    }
}
