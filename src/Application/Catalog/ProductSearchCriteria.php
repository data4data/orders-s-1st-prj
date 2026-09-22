<?php

declare(strict_types=1);

namespace App\Application\Catalog;

/**
 * Catalog filters as chosen on the catalog page.
 */
final readonly class ProductSearchCriteria
{
    public const SORTS = ['relevance', 'price_asc', 'price_desc', 'name'];

    /**
     * @param list<int>             $categoryIds a category and all categories below it
     * @param array<int, list<int>> $optionIds   attribute id => selected option ids (OR within, AND across)
     * @param list<int>             $volumesMl   pack sizes
     * @param array<int, string>    $grossFactor tax category id => "1.21" (turns net prices into gross)
     */
    public function __construct(
        public array $categoryIds = [],
        public string $search = '',
        public array $optionIds = [],
        public array $volumesMl = [],
        public ?int $minGross = null,
        public ?int $maxGross = null,
        public bool $inStockOnly = false,
        public string $sort = 'relevance',
        public int $page = 1,
        public int $perPage = 12,
        public array $grossFactor = [],
    ) {
    }

    public function withoutAttribute(int $attributeId): self
    {
        $optionIds = $this->optionIds;
        unset($optionIds[$attributeId]);

        return new self($this->categoryIds, $this->search, $optionIds, $this->volumesMl, $this->minGross, $this->maxGross, $this->inStockOnly, $this->sort, $this->page, $this->perPage, $this->grossFactor);
    }

    public function withoutVolumes(): self
    {
        return new self($this->categoryIds, $this->search, $this->optionIds, [], $this->minGross, $this->maxGross, $this->inStockOnly, $this->sort, $this->page, $this->perPage, $this->grossFactor);
    }
}
