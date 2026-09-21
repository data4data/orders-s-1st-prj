<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Query;

/**
 * Stores the staff member may pick in the admin store switcher. Result: list<AdminStoreOption>.
 */
final readonly class ListAdminStores
{
    public function __construct(public string $staffEmail)
    {
    }
}
