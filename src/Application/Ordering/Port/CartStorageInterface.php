<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

/**
 * Remembers which draft order is the visitor's cart (per store, in the session) and which orders
 * the visitor placed as a guest (so the confirmation page can be shown to them only).
 */
interface CartStorageInterface
{
    public function cartId(int $storeId): ?string;

    public function rememberCart(int $storeId, string $orderPublicId): void;

    public function forgetCart(int $storeId): void;

    public function rememberPlacedOrder(string $orderPublicId): void;

    public function placedOrderIsKnown(string $orderPublicId): bool;
}
