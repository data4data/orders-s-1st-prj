<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

/**
 * Removes an address; the last billing or delivery address cannot be removed.
 */
final readonly class DeleteAddress
{
    public function __construct(public string $id)
    {
    }
}
