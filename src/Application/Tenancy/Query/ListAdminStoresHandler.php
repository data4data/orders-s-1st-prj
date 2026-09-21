<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Query;

use App\Application\Tenancy\Exception\StoreAccessDeniedException;
use App\Application\Tenancy\Port\StaffUserRepositoryInterface;
use App\Application\Tenancy\Port\StoreMembershipRepositoryInterface;
use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Application\Tenancy\View\AdminStoreOption;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAdminStoresHandler
{
    public function __construct(
        private StaffUserRepositoryInterface $staffUsers,
        private StoreRepositoryInterface $stores,
        private StoreMembershipRepositoryInterface $memberships,
    ) {
    }

    /**
     * @return list<AdminStoreOption>
     */
    public function __invoke(ListAdminStores $query): array
    {
        $staffUser = $this->staffUsers->findByEmail($query->staffEmail)
            ?? throw new StoreAccessDeniedException('Unknown staff user.');

        $roles = [];
        foreach ($this->memberships->findForStaffUser($staffUser) as $membership) {
            $roles[$membership->getStore()->getCode()] = $membership->getRole()->value;
        }

        $stores = $staffUser->isSuperAdmin()
            ? $this->stores->findAllActive()
            : array_map(static fn ($membership) => $membership->getStore(), $this->memberships->findForStaffUser($staffUser));

        return array_map(static fn ($store) => new AdminStoreOption(
            $store->getPublicId()->toRfc4122(),
            $store->getCode(),
            $store->getName(),
            $store->getLogoUrl(),
            $roles[$store->getCode()] ?? null,
        ), $stores);
    }
}
