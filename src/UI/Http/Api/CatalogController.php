<?php

declare(strict_types=1);

namespace App\UI\Http\Api;

use App\Application\Bus\QueryBusInterface;
use App\Application\Catalog\Query\GetCategoryTree;
use App\Application\Catalog\Query\GetProductDetail;
use App\Application\Catalog\Query\SearchProducts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront catalog API used by the Vue catalog and product pages.
 */
final class CatalogController extends AbstractController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    #[Route('/api/categories', name: 'api_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetCategoryTree()));
    }

    /**
     * Example: /api/products?category=engine-oil&filters[sae_viscosity][]=5W-30&packs[]=5000&sort=price_asc&page=2.
     */
    #[Route('/api/products', name: 'api_products', methods: ['GET'])]
    public function products(#[MapQueryString] SearchProducts $query = new SearchProducts()): JsonResponse
    {
        return $this->json($this->queryBus->ask($query));
    }

    #[Route('/api/products/{slug}', name: 'api_product', methods: ['GET'])]
    public function product(string $slug): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetProductDetail($slug)));
    }
}
