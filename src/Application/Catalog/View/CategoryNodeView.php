<?php

declare(strict_types=1);

namespace App\Application\Catalog\View;

use App\Entity\Category;

final readonly class CategoryNodeView
{
    /**
     * @param list<CategoryNodeView> $children
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public array $children,
        public bool $isActive = true,
        public ?int $parentId = null,
        public int $position = 0,
        public ?string $description = null,
        public ?int $productCount = null,
    ) {
    }

    public static function fromCategory(Category $category, bool $activeOnly, ?callable $countProducts = null): self
    {
        $children = [];
        foreach ($category->getChildren() as $child) {
            if (!$activeOnly || $child->isActive()) {
                $children[] = self::fromCategory($child, $activeOnly, $countProducts);
            }
        }

        return new self(
            (int) $category->getId(),
            $category->getSlug(),
            $category->getName(),
            $children,
            $category->isActive(),
            $category->getParent()?->getId(),
            $category->getPosition(),
            $category->getDescription(),
            null !== $countProducts ? $countProducts($category) : null,
        );
    }
}
