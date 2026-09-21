<?php

declare(strict_types=1);

namespace App\Domain\Customer;

/**
 * One saved address and what it may be used for (customer_address flags).
 */
final readonly class AddressBookEntry
{
    public function __construct(
        public string $id,
        public bool $usableForBilling,
        public bool $usableForShipping,
    ) {
        if (!$usableForBilling && !$usableForShipping) {
            throw new \InvalidArgumentException(sprintf('Address "%s" must be usable for billing, delivery or both.', $id));
        }
    }
}
