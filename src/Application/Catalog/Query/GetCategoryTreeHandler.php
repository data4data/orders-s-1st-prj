<?php

declare(strict_types=1);

namespace App\Application\Catalog\Query;

use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Application\Catalog\View\CategoryNodeView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCategoryTreeHandler
{
    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    /**
     * @return list<CategoryNodeView>
     */
    public function __invoke(GetCategoryTree $query): array
    {
        return array_map(
            static fn ($category) => CategoryNodeView::fromCategory($category, true),
            $this->categories->findTopLevel(activeOnly: true),
        );
    }
}
