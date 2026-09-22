<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Removes a staff member from the selected store.
 */
final readonly class RemoveStoreStaff
{
    public function __construct(public int $membershipId)
    {
    }
}
