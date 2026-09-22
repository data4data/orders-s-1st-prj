<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * Places of the order state machine (architecture.md §4) with the customer label and admin badge.
 */
enum OrderState: string
{
    case Draft = 'draft';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** Translation key of the label customers see. */
    public function label(): string
    {
        return 'order.state.'.$this->value;
    }

    /** Badge colour in the admin (PrimeVue Tag severity or a named colour). */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::PaymentPending => 'warn',
            self::Paid => 'success',
            self::Processing => 'info',
            self::Shipped => 'indigo',
            self::Delivered => 'teal',
            self::Cancelled => 'danger',
            self::Refunded => 'purple',
        };
    }

    /** Stock is held (reserved) or already taken out for the order. */
    public function holdsStock(): bool
    {
        return \in_array($this, [self::PaymentPending, self::Paid, self::Processing, self::Shipped, self::Delivered], true);
    }
}
