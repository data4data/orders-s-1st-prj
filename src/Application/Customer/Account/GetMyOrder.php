<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

/**
 * Query: one placed order of the logged-in customer.
 */
final readonly class GetMyOrder
{
    public function __construct(public string $id)
    {
    }
}
