<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

use App\Entity\StaffUser;
use App\Entity\Store;
use App\Entity\StoreMembership;

interface StoreMembershipRepositoryInterface
{
    public function findOne(StaffUser $staffUser, Store $store): ?StoreMembership;

    /** @return list<StoreMembership> active stores only, ordered by store name */
    public function findForStaffUser(StaffUser $staffUser): array;
}
