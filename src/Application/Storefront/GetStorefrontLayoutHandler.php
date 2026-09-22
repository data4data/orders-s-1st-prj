<?php

declare(strict_types=1);

namespace App\Application\Storefront;

use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Application\Customer\CurrentCustomerInterface;
use App\Application\Ordering\CartProvider;
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
        private CategoryRepositoryInterface $categories,
        private CartProvider $carts,
        private CurrentCustomerInterface $currentCustomer,
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

        $categories = array_map(
            static fn ($category) => ['name' => $category->getName(), 'slug' => $category->getSlug()],
            $this->categories->findTopLevel(activeOnly: true),
        );

        return new StorefrontLayoutView(
            StoreView::fromStore($store),
            $otherShops,
            $categories,
            $this->carts->current()?->itemCount() ?? 0,
            $this->currentCustomer->get()?->getFirstName(),
        );
    }
}
