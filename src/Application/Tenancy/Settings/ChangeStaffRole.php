<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

/**
 * Changes a staff member's role.
 */
final readonly class ChangeStaffRole
{
    public function __construct(public int $membershipId, public string $role)
    {
    }
}
