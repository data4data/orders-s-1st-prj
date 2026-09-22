<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Catalog\View\PriceView;
use App\Application\Catalog\View\ProductCardView;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Store;

/**
 * Shared mapping from a product to what the storefront shows (cards, badges, stock status).
 */
final readonly class ProductPresenter
{
    public const IN_STOCK = 'in_stock';
    public const LOW_STOCK = 'low_stock';
    public const OUT_OF_STOCK = 'out_of_stock';

    public function __construct(private CatalogPricing $pricing)
    {
    }

    /**
     * @param (callable(ProductVariant, PriceView): bool)|null $matches only packs that match the active
     *                                                                  filters count for "from €…"
     */
    public function card(Store $store, Product $product, ?callable $matches = null): ProductCardView
    {
        $variants = $product->activeVariants();
        $cheapest = null;
        $cheapestPrice = null;
        foreach ($variants as $variant) {
            $price = $this->pricing->price($store, $variant);
            if (null !== $matches && !$matches($variant, $price)) {
                continue;
            }
            if (null === $cheapestPrice || $price->gross < $cheapestPrice->gross) {
                [$cheapest, $cheapestPrice] = [$variant, $price];
            }
        }
        $image = $product->getImages()->filter(static fn ($i) => null === $i->getVariant())->first() ?: $product->getImages()->first();

        return new ProductCardView(
            $product->getSlug(),
            $product->getName(),
            $image ? $image->getUrl() : null,
            $image ? $image->getAltText() : null,
            $this->badges($product),
            $cheapestPrice,
            $cheapest ? PackSize::label($cheapest->getVolumeMl()) : null,
            \count($variants) > 1,
            $this->productStock($store, $variants),
        );
    }

    /**
     * Up to three key specs (first select-type attributes by position), e.g. ["5W-30", "ACEA C3"].
     *
     * @return list<string>
     */
    public function badges(Product $product, int $limit = 3): array
    {
        $values = $product->getAttributeValues()->toArray();
        usort($values, static fn ($a, $b) => [$a->getAttribute()->getPosition(), $a->getOption()?->getPosition() ?? 0] <=> [$b->getAttribute()->getPosition(), $b->getOption()?->getPosition() ?? 0]);

        $badges = [];
        foreach ($values as $value) {
            if (null !== $value->getOption() && \count($badges) < $limit) {
                $badges[] = $value->getOption()->getValue();
            }
        }

        return $badges;
    }

    public function variantStock(Store $store, ProductVariant $variant): string
    {
        $stock = $variant->stock();

        return match (true) {
            0 === $stock->available() => self::OUT_OF_STOCK,
            $stock->isLow($store->getLowStockThreshold()) => self::LOW_STOCK,
            default => self::IN_STOCK,
        };
    }

    /**
     * @param list<ProductVariant> $variants
     */
    private function productStock(Store $store, array $variants): string
    {
        $statuses = array_map(fn (ProductVariant $v): string => $this->variantStock($store, $v), $variants);

        return match (true) {
            \in_array(self::IN_STOCK, $statuses, true) => self::IN_STOCK,
            \in_array(self::LOW_STOCK, $statuses, true) => self::LOW_STOCK,
            default => self::OUT_OF_STOCK,
        };
    }
}
