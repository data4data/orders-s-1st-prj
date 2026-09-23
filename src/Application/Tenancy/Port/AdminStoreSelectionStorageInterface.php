<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

use App\Application\Tenancy\AdminStoreSelection;

/**
 * Remembers the admin store switcher choice for the logged-in staff member (the session).
 */
interface AdminStoreSelectionStorageInterface
{
    public function current(): ?AdminStoreSelection;

    public function save(AdminStoreSelection $selection): void;

    public function clear(): void;
}
