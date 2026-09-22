<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

use App\Application\Customer\Input\DefaultAddressesInput;

/**
 * Chooses the default billing and delivery address.
 */
final readonly class SetDefaultAddresses
{
    public function __construct(public DefaultAddressesInput $input)
    {
    }
}
