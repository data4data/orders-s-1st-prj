<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Customer\CurrentCustomerInterface;
use App\Application\Ordering\Port\CartStorageInterface;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Tenancy\TenantContextInterface;
use App\Domain\Shared\Quantity;
use App\Entity\Customer;
use App\Entity\Order;
use Symfony\Component\Uid\Uuid;

/**
 * Finds (or starts) the visitor's cart, a draft order: the customer's own draft when logged in,
 * otherwise the draft remembered in the session.
 */
final readonly class CartProvider
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CurrentCustomerInterface $currentCustomer,
        private CartStorageInterface $storage,
        private OrderRepositoryInterface $orders,
    ) {
    }

    public function current(bool $create = false): ?Order
    {
        $store = $this->tenantContext->requireStore();
        $storeId = (int) $store->getId();
        $customer = $this->currentCustomer->get();

        $cart = null !== $customer ? $this->orders->findDraftOfCustomer($customer) : $this->sessionCart($storeId);
        if (null === $cart && $create) {
            $cart = new Order($store->getCurrencyCode());
            $cart->assignCustomer($customer);
            $this->orders->save($cart);
        }
        if (null !== $cart) {
            $this->storage->rememberCart($storeId, $cart->getPublicId()->toRfc4122());
        }

        return $cart;
    }

    /**
     * After login: the guest cart becomes the customer's cart, merged into an older one if any.
     */
    public function adoptGuestCart(Customer $customer): void
    {
        $storeId = (int) $this->tenantContext->requireStore()->getId();
        $guestCart = $this->sessionCart($storeId);
        $ownCart = $this->orders->findDraftOfCustomer($customer);
        if (null === $guestCart || $guestCart === $ownCart) {
            return;
        }

        if (null === $ownCart) {
            $guestCart->assignCustomer($customer);
            $this->orders->save($guestCart);

            return;
        }

        foreach ($guestCart->getItems() as $item) {
            if (null !== $item->getVariant()) {
                $ownCart->add($item->getVariant(), Quantity::of($item->getQuantity()));
            }
        }
        if (null === $ownCart->getCoupon() && null !== $guestCart->getCoupon()) {
            $ownCart->applyCoupon($guestCart->getCoupon());
        }
        $this->orders->save($ownCart);
        $this->orders->remove($guestCart);
        $this->storage->rememberCart($storeId, $ownCart->getPublicId()->toRfc4122());
    }

    private function sessionCart(int $storeId): ?Order
    {
        $id = $this->storage->cartId($storeId);
        if (null === $id || !Uuid::isValid($id)) {
            return null;
        }
        $cart = $this->orders->findDraftByPublicId(Uuid::fromString($id));

        // A cart that belongs to an account is only used while that customer is logged in.
        return null !== $cart && null === $cart->getCustomer() ? $cart : null;
    }
}
