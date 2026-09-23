<?php

declare(strict_types=1);

namespace App\UI\Http\Web;

use App\Application\Bus\QueryBusInterface;
use App\Application\Catalog\Query\GetProductDetail;
use App\Application\Catalog\Query\SearchProducts;
use App\Application\Catalog\View\ProductDetailView;
use App\Application\Catalog\View\ProductListView;
use App\Application\Exception\NotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront catalog pages: Twig renders the page shell with SEO-friendly title and description,
 * the Vue islands (Catalog, Product) load the data through /api (decision #20).
 */
final class CatalogPageController extends AbstractController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    #[Route('/catalog', name: 'catalog_all', methods: ['GET'])]
    #[Route('/c/{slug}', name: 'catalog_category', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function category(?string $slug = null): Response
    {
        try {
            /** @var ProductListView $list */
            $list = $this->queryBus->ask(new SearchProducts(category: $slug, perPage: 1));
        } catch (NotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->render('storefront/catalog.html.twig', [
            'title' => $list->category->name ?? null,
            'props' => ['category' => $slug],
        ]);
    }

    #[Route('/search', name: 'catalog_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        return $this->render('storefront/catalog.html.twig', [
            'title' => null,
            'props' => ['category' => null, 'q' => $request->query->getString('q')],
        ]);
    }

    #[Route('/p/{slug}', name: 'product_page', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function product(string $slug): Response
    {
        try {
            /** @var ProductDetailView $product */
            $product = $this->queryBus->ask(new GetProductDetail($slug));
        } catch (NotFoundException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->render('storefront/product.html.twig', [
            'product' => $product,
            'props' => ['slug' => $slug],
        ]);
    }
}
