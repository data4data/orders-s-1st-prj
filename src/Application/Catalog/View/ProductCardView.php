<?php

declare(strict_types=1);

namespace App\Application\Catalog\View;

/**
 * One product in the catalog grid: cheapest pack ("from €12.95"), spec chips and stock.
 */
final readonly class ProductCardView
{
    /**
     * @param list<string> $badges key specs, e.g. ["5W-30", "ACEA C3"]
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $imageUrl,
        public ?string $imageAlt,
        public array $badges,
        public ?PriceView $fromPrice,
        public ?string $fromPackName,
        public bool $multiplePacks,
        public string $stock,
    ) {
    }
}
