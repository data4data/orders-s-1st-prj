<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

/**
 * A staff member's role inside one store (store_membership.role).
 */
enum StoreRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';

    /**
     * Symfony security roles granted while this store is selected in the admin.
     *
     * @return list<string>
     */
    public function securityRoles(): array
    {
        return match ($this) {
            self::Owner => ['ROLE_STORE_STAFF', 'ROLE_STORE_MANAGER', 'ROLE_STORE_OWNER'],
            self::Manager => ['ROLE_STORE_STAFF', 'ROLE_STORE_MANAGER'],
            self::Staff => ['ROLE_STORE_STAFF'],
        };
    }
}
