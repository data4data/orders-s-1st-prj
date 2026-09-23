<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Http;

use App\Application\Tenancy\Port\AdminStoreSelectionStorageInterface;
use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Application\Tenancy\StoreAccess;
use App\Entity\StaffUser;
use App\Infrastructure\Tenancy\AdminHost;
use App\Infrastructure\Tenancy\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the admin store switcher choice for requests on the admin host.
 *
 * Priority 7 runs right after the firewall (8), so the staff member is known and the choice
 * is re-checked against their memberships on every request.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class AdminTenantListener
{
    public function __construct(
        private Security $security,
        private AdminStoreSelectionStorageInterface $selectionStorage,
        private StoreRepositoryInterface $stores,
        private StoreAccess $storeAccess,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !AdminHost::matches($event->getRequest()->getHost())) {
            return;
        }

        $this->tenantContext->clear();

        $staffUser = $this->security->getUser();
        $selection = $this->selectionStorage->current();
        if (!$staffUser instanceof StaffUser || null === $selection) {
            return;
        }

        if ($selection->isAllStores()) {
            if ($staffUser->isSuperAdmin()) {
                $this->tenantContext->usePlatform(readOnly: true);
            } else {
                $this->selectionStorage->clear();
            }

            return;
        }

        $store = null !== $selection->storeId ? $this->stores->findById($selection->storeId) : null;
        if (null === $store || !$store->isActive() || !$this->storeAccess->canAccess($staffUser, $store)) {
            $this->selectionStorage->clear(); // membership removed or store deactivated meanwhile

            return;
        }

        $this->tenantContext->useStore($store);
    }
}
