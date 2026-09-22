<?php

declare(strict_types=1);

namespace App\Application\Storefront;

use App\Application\Tenancy\View\StoreView;

/**
 * Data for the storefront header and footer. One object, rendered by the Twig partials and
 * handed as JSON to the Vue header/footer, so both stacks show exactly the same thing.
 */
final readonly class StorefrontLayoutView
{
    /**
     * @param list<array{code: string, name: string, host: string}> $otherShops
     * @param list<array{name: string, slug: string}>               $categories top-level categories (Phase 4)
     */
    public function __construct(
        public StoreView $store,
        public array $otherShops,
        public array $categories,
        public int $cartItemCount,
    ) {
    }
}
