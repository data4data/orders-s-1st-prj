<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Bus\QueryBusInterface;
use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Application\Catalog\Query\SearchProducts;
use App\Application\Catalog\View\ProductListView;
use App\Application\Tenancy\TenantContextInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetLandingPageHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CategoryRepositoryInterface $categories,
        private QueryBusInterface $queryBus,
        private ShippingInfo $shippingInfo,
    ) {
    }

    public function __invoke(GetLandingPage $query): LandingPageView
    {
        $store = $this->tenantContext->requireStore();
        /** @var ProductListView $all */
        $all = $this->queryBus->ask(new SearchProducts(inStock: true, perPage: 4));

        $categories = [];
        foreach ($this->categories->findTopLevel(activeOnly: true) as $category) {
            /** @var ProductListView $list */
            $list = $this->queryBus->ask(new SearchProducts(category: $category->getSlug(), perPage: 1));
            $categories[] = ['slug' => $category->getSlug(), 'name' => $category->getName(), 'productCount' => $list->total, 'imageUrl' => $list->items[0]->imageUrl ?? null];
        }

        $facet = $all->facets[0] ?? null;

        return new LandingPageView(
            $categories,
            $all->items,
            null === $facet ? null : ['code' => $facet['code'], 'name' => $facet['name'], 'options' => array_column($facet['options'], 'value')],
            $this->shippingInfo->summary(),
            $store->getCurrencyCode(),
        );
    }
}
