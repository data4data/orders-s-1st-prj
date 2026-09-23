<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

/**
 * Query: placed orders of the selected store for the admin list.
 */
final readonly class ListAdminOrders
{
    public function __construct(public ?string $state = null, public string $q = '', public int $page = 1)
    {
    }
}
