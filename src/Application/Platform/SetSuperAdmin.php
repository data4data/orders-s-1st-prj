<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Grants or revokes super-admin rights.
 */
final readonly class SetSuperAdmin
{
    public function __construct(public int $id, public bool $superAdmin)
    {
    }
}
