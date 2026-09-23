<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Gives an existing staff user a role in the selected store.
 */
final readonly class AddStoreStaff
{
    public function __construct(public StaffMemberInput $input)
    {
    }
}
