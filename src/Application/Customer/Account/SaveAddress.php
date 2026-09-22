<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

use App\Application\Customer\Input\AddressInput;

/**
 * Adds (id null) or changes an address in the address book.
 */
final readonly class SaveAddress
{
    public function __construct(public ?string $id, public AddressInput $input)
    {
    }
}
