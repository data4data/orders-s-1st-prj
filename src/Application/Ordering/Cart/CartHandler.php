<?php

declare(strict_types=1);

namespace App\Application\Ordering\Cart;

use App\Application\Catalog\Port\ProductRepositoryInterface;
use App\Application\Exception\NotFoundException;
use App\Application\Ordering\CartPresenter;
use App\Application\Ordering\CartProvider;
use App\Application\Ordering\OrderPricer;
use App\Application\Ordering\Port\CouponRepositoryInterface;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Ordering\View\CartView;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Shared\Quantity;
use App\Entity\Order;
use App\Entity\ProductVariant;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The cart: add, change, remove lines and apply a coupon. Every action answers with the new cart.
 */
final readonly class CartHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CartProvider $carts,
        private CartPresenter $presenter,
        private OrderPricer $pricer,
        private OrderRepositoryInterface $orders,
        private ProductRepositoryInterface $products,
        private CouponRepositoryInterface $coupons,
    ) {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetCart $query): CartView
    {
        return $this->view($this->carts->current());
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function add(AddToCart $command): CartView
    {
        $variant = $this->variant($command->variantId);
        $this->assertQuantity($command->quantity);
        $cart = $this->carts->current(create: true) ?? throw new \LogicException('No cart.');

        $wanted = $command->quantity + ($cart->findItem($command->variantId)?->getQuantity() ?? 0);
        $this->assertInStock($variant, $wanted, 'quantity');
        $cart->add($variant, Quantity::of($command->quantity));
        $this->orders->save($cart);

        return $this->view($cart);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdateCartLine $command): CartView
    {
        $cart = $this->carts->current();
        $item = $cart?->findItem($command->variantId) ?? throw NotFoundException::of('Cart line', $command->variantId);
        $this->assertQuantity($command->quantity);
        if (null !== $item->getVariant()) {
            $this->assertInStock($item->getVariant(), $command->quantity, 'quantity');
        }
        $item->changeQuantity($command->quantity);
        $this->orders->save($cart);

        return $this->view($cart);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function remove(RemoveCartLine $command): CartView
    {
        $cart = $this->carts->current();
        $item = $cart?->findItem($command->variantId);
        if (null === $cart || null === $item) {
            // Removing twice (double click, second tab) is not an error.
            return $this->view($cart);
        }
        $cart->remove($item);
        $this->orders->save($cart);

        return $this->view($cart);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function applyCoupon(ApplyCoupon $command): CartView
    {
        $cart = $this->carts->current();
        if (null === $cart || 0 === $cart->getItems()->count()) {
            throw ValidationException::forField('code', 'Add products to your cart first.');
        }
        $coupon = '' !== trim($command->code) ? $this->coupons->findByCode($command->code) : null;
        if (null === $coupon) {
            throw ValidationException::forField('code', 'This code is not valid.');
        }

        $cart->applyCoupon($coupon);
        $error = $this->pricer->price($this->tenantContext->requireStore(), $cart)->couponError;
        if (null !== $error) {
            // Keep the cart as it was and tell why the code cannot be used now.
            throw ValidationException::forField('code', CouponMessages::for($error));
        }
        $this->orders->save($cart);

        return $this->view($cart);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function removeCoupon(RemoveCoupon $command): CartView
    {
        $cart = $this->carts->current();
        if (null !== $cart && null !== $cart->getCoupon()) {
            $cart->applyCoupon(null);
            $this->orders->save($cart);
        }

        return $this->view($cart);
    }

    private function view(?Order $cart): CartView
    {
        return $this->presenter->present($this->tenantContext->requireStore(), $cart);
    }

    private function variant(string $id): ProductVariant
    {
        $variant = Uuid::isValid($id) ? $this->products->findVariantByPublicId(Uuid::fromString($id)) : null;
        if (null === $variant || !$variant->isActive() || !$variant->getProduct()->isActive()) {
            throw NotFoundException::of('Product', $id);
        }

        return $variant;
    }

    private function assertQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > Order::MAX_QUANTITY) {
            throw ValidationException::forField('quantity', sprintf('Choose a quantity between 1 and %d.', Order::MAX_QUANTITY));
        }
    }

    private function assertInStock(ProductVariant $variant, int $quantity, string $field): void
    {
        $available = $variant->stock()->available();
        if ($quantity > $available) {
            throw ValidationException::forField($field, 0 === $available ? 'This pack size is out of stock.' : sprintf('Only %d available.', $available));
        }
    }
}
