<?php

declare(strict_types=1);

namespace App\Application\Catalog\Port;

use App\Entity\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    /** @return list<Category> top-level categories, ordered */
    public function findTopLevel(bool $activeOnly): array;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    public function countProducts(Category $category): int;

    public function save(Category $category): void;

    public function remove(Category $category): void;
}
