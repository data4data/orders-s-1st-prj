<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Command;

/**
 * Selects the store the admin works in. A null store means "All stores" (super-admin only).
 */
final readonly class SwitchAdminStore
{
    public function __construct(
        public string $staffEmail,
        public ?string $storePublicId,
    ) {
    }
}
