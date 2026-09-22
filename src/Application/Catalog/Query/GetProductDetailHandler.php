<?php

declare(strict_types=1);

namespace App\Application\Catalog\Query;

use App\Application\Catalog\CatalogPricing;
use App\Application\Catalog\Port\ProductRepositoryInterface;
use App\Application\Catalog\ProductPresenter;
use App\Application\Catalog\View\ProductDetailView;
use App\Application\Exception\NotFoundException;
use App\Application\Tenancy\TenantContextInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetProductDetailHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private ProductRepositoryInterface $products,
        private CatalogPricing $pricing,
        private ProductPresenter $presenter,
    ) {
    }

    public function __invoke(GetProductDetail $query): ProductDetailView
    {
        $store = $this->tenantContext->requireStore();
        $product = $this->products->findActiveBySlug($query->slug);
        if (null === $product || [] === $product->activeVariants()) {
            throw NotFoundException::of('Product', $query->slug);
        }

        $variants = array_map(fn ($variant) => [
            'publicId' => $variant->getPublicId()->toRfc4122(),
            'sku' => $variant->getSku(),
            'name' => $variant->getName(),
            'volumeMl' => $variant->getVolumeMl(),
            'price' => $this->pricing->price($store, $variant),
            'stock' => $this->presenter->variantStock($store, $variant),
            'available' => $variant->stock()->available(),
        ], $product->activeVariants());

        $specs = [];
        foreach ($product->getAttributeValues() as $value) {
            $attribute = $value->getAttribute();
            $key = $attribute->getPosition().'-'.$attribute->getCode();
            $specs[$key] ??= ['name' => $attribute->getName(), 'values' => []];
            $specs[$key]['values'][] = $value->display();
        }
        ksort($specs, \SORT_NATURAL);

        $mainCategory = $product->getCategories()->first() ?: null;

        return new ProductDetailView(
            $product->getPublicId()->toRfc4122(),
            $product->getSlug(),
            $product->getName(),
            $product->getBrand(),
            $product->getDescription(),
            $variants,
            array_map(static fn ($image) => ['url' => $image->getUrl(), 'alt' => $image->getAltText(), 'variantPublicId' => $image->getVariant()?->getPublicId()->toRfc4122()], array_values($product->getImages()->toArray())),
            $this->presenter->badges($product, 4),
            array_values(array_map(static fn ($spec) => ['name' => $spec['name'], 'value' => implode(', ', $spec['values'])], $specs)),
            array_map(static fn ($document) => ['type' => $document->getType()->value, 'title' => $document->getTitle(), 'url' => $document->getUrl(), 'locale' => $document->getLocale()], array_values($product->getDocuments()->toArray())),
            null !== $mainCategory ? array_map(static fn ($c) => ['slug' => $c->getSlug(), 'name' => $c->getName()], $mainCategory->path()) : [],
            array_map(fn ($related) => $this->presenter->card($store, $related), $this->products->findRelated($product, 4)),
            $store->getCurrencyCode(),
        );
    }
}
