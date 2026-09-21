<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Command;

use App\Application\Tenancy\AdminStoreSelection;
use App\Application\Tenancy\Exception\StoreAccessDeniedException;
use App\Application\Tenancy\Exception\StoreNotFoundException;
use App\Application\Tenancy\Port\AdminStoreSelectionStorageInterface;
use App\Application\Tenancy\Port\StaffUserRepositoryInterface;
use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Application\Tenancy\StoreAccess;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SwitchAdminStoreHandler
{
    public function __construct(
        private StaffUserRepositoryInterface $staffUsers,
        private StoreRepositoryInterface $stores,
        private StoreAccess $storeAccess,
        private AdminStoreSelectionStorageInterface $selectionStorage,
    ) {
    }

    public function __invoke(SwitchAdminStore $command): void
    {
        $staffUser = $this->staffUsers->findByEmail($command->staffEmail)
            ?? throw new StoreAccessDeniedException('Unknown staff user.');

        if (null === $command->storePublicId) {
            if (!$staffUser->isSuperAdmin()) {
                throw StoreAccessDeniedException::forAllStores();
            }
            $this->selectionStorage->save(AdminStoreSelection::allStores());

            return;
        }

        $store = Uuid::isValid($command->storePublicId)
            ? $this->stores->findByPublicId(Uuid::fromString($command->storePublicId))
            : null;
        if (null === $store || !$store->isActive()) {
            throw StoreNotFoundException::withPublicId($command->storePublicId);
        }
        if (!$this->storeAccess->canAccess($staffUser, $store)) {
            throw StoreAccessDeniedException::forStore($store->getCode());
        }

        $this->selectionStorage->save(AdminStoreSelection::store((int) $store->getId()));
    }
}
