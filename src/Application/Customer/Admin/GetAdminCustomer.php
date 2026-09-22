<?php

declare(strict_types=1);

namespace App\Application\Customer\Admin;

/**
 * Query: one customer with addresses and orders.
 */
final readonly class GetAdminCustomer
{
    public function __construct(public string $id)
    {
    }
}
