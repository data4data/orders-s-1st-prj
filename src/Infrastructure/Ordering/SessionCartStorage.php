<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering;

use App\Application\Ordering\Port\CartStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Cart id and guest orders in the session. Each shop has its own host and therefore its own
 * session cookie; the store id in the key is an extra safety net.
 */
final readonly class SessionCartStorage implements CartStorageInterface
{
    private const PLACED = 'placed_orders';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function cartId(int $storeId): ?string
    {
        $id = $this->requestStack->getSession()->get('cart.'.$storeId);

        return \is_string($id) ? $id : null;
    }

    public function rememberCart(int $storeId, string $orderPublicId): void
    {
        $this->requestStack->getSession()->set('cart.'.$storeId, $orderPublicId);
    }

    public function forgetCart(int $storeId): void
    {
        $this->requestStack->getSession()->remove('cart.'.$storeId);
    }

    public function rememberPlacedOrder(string $orderPublicId): void
    {
        $session = $this->requestStack->getSession();
        $placed = $session->get(self::PLACED, []);
        $placed = \is_array($placed) ? $placed : [];
        $placed[] = $orderPublicId;
        // The last few are enough to show the confirmation and a guest's recent orders.
        $session->set(self::PLACED, \array_slice(array_values(array_unique($placed)), -10));
    }

    public function placedOrderIsKnown(string $orderPublicId): bool
    {
        $placed = $this->requestStack->getSession()->get(self::PLACED, []);

        return \is_array($placed) && \in_array($orderPublicId, $placed, true);
    }
}
