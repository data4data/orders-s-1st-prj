<?php

declare(strict_types=1);

namespace App\Domain\Customer;

/**
 * Why a change to the address book is refused. The UI turns each case into a message.
 */
enum AddressBookViolation: string
{
    case MissingBillingAddress = 'missing_billing_address';
    case MissingDeliveryAddress = 'missing_delivery_address';
    case UnknownAddress = 'unknown_address';
    case DefaultNotUsable = 'default_not_usable';
}
