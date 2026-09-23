<?php

declare(strict_types=1);

namespace App\Application\Catalog\View;

final readonly class ProductListView
{
    /**
     * @param list<ProductCardView>                                                                                                   $items
     * @param list<array{code: string, name: string, unit: ?string, options: list<array{value: string, count: int, selected: bool}>}> $facets
     * @param list<array{volumeMl: int, label: string, count: int, selected: bool}>                                                   $packSizes
     * @param list<array{slug: string, name: string}>                                                                                 $breadcrumbs
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public array $facets,
        public array $packSizes,
        public ?CategoryNodeView $category,
        public array $breadcrumbs,
        public string $currency,
    ) {
    }
}
