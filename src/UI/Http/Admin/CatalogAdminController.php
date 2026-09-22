<?php

declare(strict_types=1);

namespace App\UI\Http\Admin;

use App\Application\Bus\CommandBusInterface;
use App\Application\Bus\QueryBusInterface;
use App\Application\Catalog\Admin\DeleteAttribute;
use App\Application\Catalog\Admin\DeleteCategory;
use App\Application\Catalog\Admin\DeleteProduct;
use App\Application\Catalog\Admin\GetAdminProduct;
use App\Application\Catalog\Admin\ListAdminCategories;
use App\Application\Catalog\Admin\ListAdminProducts;
use App\Application\Catalog\Admin\ListAttributes;
use App\Application\Catalog\Admin\ListTaxCategories;
use App\Application\Catalog\Admin\SaveAttribute;
use App\Application\Catalog\Admin\SaveCategory;
use App\Application\Catalog\Admin\SaveProduct;
use App\Application\Catalog\Input\AttributeInput;
use App\Application\Catalog\Input\CategoryInput;
use App\Application\Catalog\Input\ProductInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin catalog API for the selected store (Catalog → Products, Categories, Attributes).
 * Writing needs a store role (StoreRoleVoter); the read-only "All stores" view cannot write.
 */
#[Route('/api/admin/catalog')]
#[IsGranted('ROLE_STORE_STAFF')]
final class CatalogAdminController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/tax-categories', name: 'admin_tax_categories', methods: ['GET'])]
    public function taxCategories(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListTaxCategories()));
    }

    #[Route('/categories', name: 'admin_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAdminCategories()));
    }

    #[Route('/categories', name: 'admin_category_create', methods: ['POST'])]
    public function createCategory(#[MapRequestPayload] CategoryInput $input): JsonResponse
    {
        return $this->json(['id' => $this->commandBus->dispatch(new SaveCategory(null, $input))], 201);
    }

    #[Route('/categories/{id<\d+>}', name: 'admin_category_update', methods: ['PUT'])]
    public function updateCategory(int $id, #[MapRequestPayload] CategoryInput $input): JsonResponse
    {
        return $this->json(['id' => $this->commandBus->dispatch(new SaveCategory($id, $input))]);
    }

    #[Route('/categories/{id<\d+>}', name: 'admin_category_delete', methods: ['DELETE'])]
    public function deleteCategory(int $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteCategory($id));

        return new JsonResponse(null, 204);
    }

    #[Route('/attributes', name: 'admin_attributes', methods: ['GET'])]
    public function attributes(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAttributes()));
    }

    #[Route('/attributes', name: 'admin_attribute_create', methods: ['POST'])]
    public function createAttribute(#[MapRequestPayload] AttributeInput $input): JsonResponse
    {
        return $this->json(['id' => $this->commandBus->dispatch(new SaveAttribute(null, $input))], 201);
    }

    #[Route('/attributes/{id<\d+>}', name: 'admin_attribute_update', methods: ['PUT'])]
    public function updateAttribute(int $id, #[MapRequestPayload] AttributeInput $input): JsonResponse
    {
        return $this->json(['id' => $this->commandBus->dispatch(new SaveAttribute($id, $input))]);
    }

    #[Route('/attributes/{id<\d+>}', name: 'admin_attribute_delete', methods: ['DELETE'])]
    public function deleteAttribute(int $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteAttribute($id));

        return new JsonResponse(null, 204);
    }

    #[Route('/products', name: 'admin_products', methods: ['GET'])]
    public function products(Request $request): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListAdminProducts(
            $request->query->getString('q'),
            max(1, $request->query->getInt('page', 1)),
            min(100, max(1, $request->query->getInt('perPage', 20))),
        )));
    }

    #[Route('/products', name: 'admin_product_create', methods: ['POST'])]
    public function createProduct(#[MapRequestPayload] ProductInput $input): JsonResponse
    {
        return $this->json(['publicId' => $this->commandBus->dispatch(new SaveProduct(null, $input))], 201);
    }

    #[Route('/products/{publicId}', name: 'admin_product', methods: ['GET'])]
    public function product(string $publicId): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetAdminProduct($publicId)));
    }

    #[Route('/products/{publicId}', name: 'admin_product_update', methods: ['PUT'])]
    public function updateProduct(string $publicId, #[MapRequestPayload] ProductInput $input): JsonResponse
    {
        return $this->json(['publicId' => $this->commandBus->dispatch(new SaveProduct($publicId, $input))]);
    }

    #[Route('/products/{publicId}', name: 'admin_product_delete', methods: ['DELETE'])]
    public function deleteProduct(string $publicId): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteProduct($publicId));

        return new JsonResponse(null, 204);
    }
}
