<?php

declare(strict_types=1);

namespace App\Application\Catalog\Query;

/**
 * Product page data. Result: ProductDetailView (NotFoundException when the product is not sold).
 */
final readonly class GetProductDetail
{
    public function __construct(public string $slug)
    {
    }
}
