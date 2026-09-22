<?php

declare(strict_types=1);

namespace App\Application\Catalog\View;

/**
 * Everything the product page shows (docs/diagrams/pages.html → Catalog & product).
 */
final readonly class ProductDetailView
{
    /**
     * @param list<array{publicId: string, sku: string, name: string, volumeMl: int, price: PriceView, stock: string, available: int}> $variants
     * @param list<array{url: string, alt: string, variantPublicId: ?string}>                                                          $images
     * @param list<string>                                                                                                             $badges
     * @param list<array{name: string, value: string}>                                                                                 $specs
     * @param list<array{type: string, title: string, url: string, locale: string}>                                                    $documents
     * @param list<array{slug: string, name: string}>                                                                                  $breadcrumbs
     * @param list<ProductCardView>                                                                                                    $related
     */
    public function __construct(
        public string $publicId,
        public string $slug,
        public string $name,
        public string $brand,
        public ?string $description,
        public array $variants,
        public array $images,
        public array $badges,
        public array $specs,
        public array $documents,
        public array $breadcrumbs,
        public array $related,
        public string $currency,
    ) {
    }
}
