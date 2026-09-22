<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Application\Catalog\View\CategoryNodeView;
use App\Application\Exception\NotFoundException;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Entity\Category;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Admin: the store's category tree.
 */
final readonly class CategoryAdminHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CategoryRepositoryInterface $categories,
    ) {
    }

    /**
     * @return list<CategoryNodeView>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListAdminCategories $query): array
    {
        $this->tenantContext->requireStore();

        return array_map(
            fn (Category $category) => CategoryNodeView::fromCategory($category, false, $this->categories->countProducts(...)),
            $this->categories->findTopLevel(activeOnly: false),
        );
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveCategory $command): int
    {
        $this->tenantContext->requireStore();
        $input = $command->input;

        $category = null;
        if (null !== $command->id) {
            $category = $this->categories->findById($command->id) ?? throw NotFoundException::of('Category', (string) $command->id);
        }
        if ($this->categories->slugExists($input->slug, $category?->getId())) {
            throw ValidationException::forField('slug', 'Another category already uses this URL name.');
        }

        $parent = null;
        if (null !== $input->parentId) {
            $parent = $this->categories->findById($input->parentId) ?? throw ValidationException::forField('parentId', 'Choose an existing parent category.');
        }

        $category ??= new Category($input->slug, $input->name);
        $category->update($input->slug, $input->name, $input->description, $input->position, $input->isActive);
        try {
            $category->moveTo($parent);
        } catch (\DomainException $exception) {
            throw ValidationException::forField('parentId', $exception->getMessage());
        }
        $this->categories->save($category);

        return (int) $category->getId();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteCategory $command): void
    {
        $this->tenantContext->requireStore();
        $category = $this->categories->findById($command->id) ?? throw NotFoundException::of('Category', (string) $command->id);

        if (!$category->getChildren()->isEmpty()) {
            throw new \DomainException(sprintf('"%s" has sub-categories. Move or delete them first.', $category->getName()));
        }
        $products = $this->categories->countProducts($category);
        if ($products > 0) {
            throw new \DomainException(sprintf('"%s" still contains %d product(s). Remove them from the category first.', $category->getName(), $products));
        }
        $this->categories->remove($category);
    }
}
