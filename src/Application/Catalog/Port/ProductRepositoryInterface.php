<?php

declare(strict_types=1);

namespace App\Application\Catalog\Port;

use App\Entity\Product;
use App\Entity\ProductVariant;
use Symfony\Component\Uid\Uuid;

interface ProductRepositoryInterface
{
    public function findByPublicId(Uuid $publicId): ?Product;

    public function findActiveBySlug(string $slug): ?Product;

    public function findVariantByPublicId(Uuid $publicId): ?ProductVariant;

    public function slugExists(string $slug, ?int $exceptProductId = null): bool;

    /**
     * SKUs from $skus that already belong to another product of the store.
     *
     * @param list<string> $skus
     *
     * @return list<string>
     */
    public function skusUsedElsewhere(array $skus, ?int $exceptProductId = null): array;

    /**
     * Products with variants, images and specs loaded, in the order of $ids.
     *
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    public function findForListing(array $ids): array;

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function adminPage(string $search, int $page, int $perPage): array;

    /** @return list<Product> other active products sharing a category */
    public function findRelated(Product $product, int $limit): array;

    public function save(Product $product): void;

    public function remove(Product $product): void;
}
