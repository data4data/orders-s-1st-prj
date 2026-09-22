<?php

declare(strict_types=1);

namespace App\Application\Catalog\Query;

use App\Application\Catalog\CatalogPricing;
use App\Application\Catalog\PackSize;
use App\Application\Catalog\Port\AttributeRepositoryInterface;
use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Application\Catalog\Port\ProductRepositoryInterface;
use App\Application\Catalog\Port\ProductSearchInterface;
use App\Application\Catalog\Port\TaxCategoryRepositoryInterface;
use App\Application\Catalog\ProductPresenter;
use App\Application\Catalog\ProductSearchCriteria;
use App\Application\Catalog\View\CategoryNodeView;
use App\Application\Catalog\View\ProductListView;
use App\Application\Exception\NotFoundException;
use App\Application\Tenancy\TenantContextInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchProductsHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CategoryRepositoryInterface $categories,
        private AttributeRepositoryInterface $attributes,
        private TaxCategoryRepositoryInterface $taxCategories,
        private ProductRepositoryInterface $products,
        private ProductSearchInterface $search,
        private CatalogPricing $pricing,
        private ProductPresenter $presenter,
    ) {
    }

    public function __invoke(SearchProducts $query): ProductListView
    {
        $store = $this->tenantContext->requireStore();

        $category = null;
        if (null !== $query->category && '' !== $query->category) {
            $category = $this->categories->findBySlug($query->category);
            if (null === $category || !$category->isActive()) {
                throw NotFoundException::of('Category', $query->category);
            }
        }

        $filterable = array_filter($this->attributes->findAllOrdered(), static fn ($a) => $a->isFilterable() && $a->getType()->usesOptions());
        $optionIds = [];
        foreach ($filterable as $attribute) {
            foreach ($query->filters[$attribute->getCode()] ?? [] as $value) {
                $option = $attribute->findOption($value);
                if (null !== $option) {
                    $optionIds[(int) $attribute->getId()][] = (int) $option->getId();
                }
            }
        }

        $criteria = new ProductSearchCriteria(
            categoryIds: null !== $category ? array_values(array_map(static fn ($c) => (int) $c->getId(), array_filter($category->withDescendants(), static fn ($c) => $c->isActive()))) : [],
            search: trim($query->q),
            optionIds: $optionIds,
            volumesMl: array_map('intval', $query->packs),
            minGross: $query->minPrice,
            maxGross: $query->maxPrice,
            inStockOnly: $query->inStock,
            sort: $query->sort,
            page: $query->page,
            perPage: $query->perPage,
            grossFactor: $this->pricing->grossFactors($store, $this->taxCategories->findAll()),
        );

        $result = $this->search->search($criteria);
        // With pack, price or stock filters, "from €…" shows the cheapest pack that matches them.
        $matches = static fn ($variant, $price): bool => ([] === $criteria->volumesMl || \in_array($variant->getVolumeMl(), $criteria->volumesMl, true))
            && (null === $criteria->minGross || $price->gross >= $criteria->minGross)
            && (null === $criteria->maxGross || $price->gross <= $criteria->maxGross)
            && (!$criteria->inStockOnly || $variant->stock()->available() > 0);
        $cards = array_map(fn ($product) => $this->presenter->card($store, $product, $matches), $this->products->findForListing($result['ids']));

        $facets = [];
        foreach ($filterable as $attribute) {
            $counts = $this->search->optionCounts($criteria->withoutAttribute((int) $attribute->getId()), (int) $attribute->getId());
            $selected = $optionIds[(int) $attribute->getId()] ?? [];
            $options = [];
            foreach ($attribute->getOptions() as $option) {
                $count = $counts[(int) $option->getId()] ?? 0;
                $isSelected = \in_array((int) $option->getId(), $selected, true);
                if ($count > 0 || $isSelected) {
                    $options[] = ['value' => $option->getValue(), 'count' => $count, 'selected' => $isSelected];
                }
            }
            if ([] !== $options) {
                $facets[] = ['code' => $attribute->getCode(), 'name' => $attribute->getName(), 'unit' => $attribute->getUnit(), 'options' => $options];
            }
        }

        $packSizes = [];
        foreach ($this->search->volumeCounts($criteria->withoutVolumes()) as $volume => $count) {
            $packSizes[] = ['volumeMl' => $volume, 'label' => PackSize::label($volume), 'count' => $count, 'selected' => \in_array($volume, $criteria->volumesMl, true)];
        }

        return new ProductListView(
            $cards,
            $result['total'],
            $criteria->page,
            $criteria->perPage,
            $facets,
            $packSizes,
            null !== $category ? CategoryNodeView::fromCategory($category, true) : null,
            null !== $category ? array_map(static fn ($c) => ['slug' => $c->getSlug(), 'name' => $c->getName()], $category->path()) : [],
            $store->getCurrencyCode(),
        );
    }
}
