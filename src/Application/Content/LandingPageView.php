<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Catalog\View\ProductCardView;

final readonly class LandingPageView
{
    /**
     * @param list<array{slug: string, name: string, productCount: int, imageUrl: ?string}> $categories
     * @param list<ProductCardView>                                                         $featured
     * @param array{code: string, name: string, options: list<string>}|null                 $finder     the first filterable attribute (SAE for cars, ISO VG for industry)
     * @param list<array{name: string, gross: ?int, freeFrom: ?int}>                        $shipping
     */
    public function __construct(
        public array $categories,
        public array $featured,
        public ?array $finder,
        public array $shipping,
        public string $currency,
    ) {
    }
}
