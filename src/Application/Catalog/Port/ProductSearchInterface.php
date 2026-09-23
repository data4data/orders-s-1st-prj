<?php

declare(strict_types=1);

namespace App\Application\Catalog\Port;

use App\Application\Catalog\ProductSearchCriteria;

/**
 * Catalog search on the database (filters, sorting, paging and facet counts).
 */
interface ProductSearchInterface
{
    /**
     * @return array{ids: list<int>, total: int}
     */
    public function search(ProductSearchCriteria $criteria): array;

    /**
     * Number of matching products per option of one attribute (the attribute's own filter ignored).
     *
     * @return array<int, int> option id => product count
     */
    public function optionCounts(ProductSearchCriteria $criteria, int $attributeId): array;

    /**
     * @return array<int, int> volume in ml => product count
     */
    public function volumeCounts(ProductSearchCriteria $criteria): array;
}
