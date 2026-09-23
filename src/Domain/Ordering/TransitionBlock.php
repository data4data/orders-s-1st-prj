<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * Why an order transition is not allowed (shown to staff as the reason).
 */
enum TransitionBlock: string
{
    case NoLines = 'no_lines';
    case AddressMissing = 'address_missing';
    case ShippingMethodMissing = 'shipping_method_missing';
    case NotPaid = 'not_paid';
    case StaffOnly = 'staff_only';
    case NotRefunded = 'not_refunded';

    public function message(): string
    {
        return match ($this) {
            self::NoLines => 'The order has no lines.',
            self::AddressMissing => 'The billing and delivery address are required.',
            self::ShippingMethodMissing => 'A shipping method is required.',
            self::NotPaid => 'The payment has not been captured for the full amount.',
            self::StaffOnly => 'Only staff of this store can do this.',
            self::NotRefunded => 'The payment has not been refunded in full.',
        };
    }
}
