<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Query;

use App\Application\Tenancy\TenantContextInterface;
use App\Application\Tenancy\View\StoreView;
use App\Application\Tenancy\View\TenantStatusView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTenantStatusHandler
{
    public function __construct(private TenantContextInterface $tenantContext)
    {
    }

    public function __invoke(GetTenantStatus $query): TenantStatusView
    {
        if ($this->tenantContext->isPlatformMode()) {
            return new TenantStatusView(TenantStatusView::MODE_ALL_STORES, null, $this->tenantContext->isReadOnly());
        }

        $store = $this->tenantContext->getStore();

        return null === $store
            ? new TenantStatusView(TenantStatusView::MODE_NONE, null, false)
            : new TenantStatusView(TenantStatusView::MODE_STORE, StoreView::fromStore($store), false);
    }
}
