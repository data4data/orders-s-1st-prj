<?php

declare(strict_types=1);

namespace App\Application\Storefront;

use App\Application\Tenancy\Port\StoreRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Tenancy\View\StoreView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetStorefrontLayoutHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private StoreRepositoryInterface $stores,
    ) {
    }

    public function __invoke(GetStorefrontLayout $query): ?StorefrontLayoutView
    {
        $store = $this->tenantContext->getStore();
        if (null === $store) {
            return null;
        }

        $hosts = $this->stores->findPrimaryHosts();
        $otherShops = [];
        foreach ($this->stores->findAllActive() as $other) {
            if ($other->getId() !== $store->getId() && isset($hosts[(int) $other->getId()])) {
                $otherShops[] = ['code' => $other->getCode(), 'name' => $other->getName(), 'host' => $hosts[(int) $other->getId()]];
            }
        }

        // Categories and the cart arrive in Phases 4 and 5.
        return new StorefrontLayoutView(StoreView::fromStore($store), $otherShops, [], 0);
    }
}
