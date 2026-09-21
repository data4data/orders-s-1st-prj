<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Application\Tenancy\Port\StoreMembershipRepositoryInterface;
use App\Entity\StaffUser;
use App\Entity\Store;

/**
 * Decides which stores a staff user may work in: super-admins may use every store,
 * everyone else only the stores they have a membership for.
 */
final readonly class StoreAccess
{
    public function __construct(private StoreMembershipRepositoryInterface $memberships)
    {
    }

    public function canAccess(StaffUser $staffUser, Store $store): bool
    {
        return $staffUser->isSuperAdmin() || null !== $this->memberships->findOne($staffUser, $store);
    }
}
