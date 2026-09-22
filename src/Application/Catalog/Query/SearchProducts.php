<?php

declare(strict_types=1);

namespace App\Application\Catalog\Query;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catalog page query (filters from the URL). Result: ProductListView.
 */
final readonly class SearchProducts
{
    /**
     * @param array<string, list<string>> $filters attribute code => selected option values
     * @param list<int>                   $packs   volumes in ml
     */
    public function __construct(
        public ?string $category = null,
        #[Assert\Length(max: 100)]
        public string $q = '',
        public array $filters = [],
        public array $packs = [],
        #[Assert\PositiveOrZero]
        public ?int $minPrice = null,
        #[Assert\PositiveOrZero]
        public ?int $maxPrice = null,
        public bool $inStock = false,
        #[Assert\Choice(choices: ['relevance', 'price_asc', 'price_desc', 'name'])]
        public string $sort = 'relevance',
        #[Assert\Range(min: 1, max: 500)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 48)]
        public int $perPage = 12,
    ) {
    }
}
