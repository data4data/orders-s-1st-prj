<?php

declare(strict_types=1);

namespace App\Application\Customer\Admin;

/**
 * Query: customers of the selected store with order count and amount spent.
 */
final readonly class ListAdminCustomers
{
    public function __construct(public string $q = '', public int $page = 1)
    {
    }
}
