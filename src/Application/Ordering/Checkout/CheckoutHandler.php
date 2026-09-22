<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

use App\Application\Customer\AddressWriter;
use App\Application\Customer\CurrentCustomerInterface;
use App\Application\Customer\Port\CountryRepositoryInterface;
use App\Application\Customer\View\AddressView;
use App\Application\Exception\NotFoundException;
use App\Application\Ordering\Cart\CouponMessages;
use App\Application\Ordering\CartPresenter;
use App\Application\Ordering\CartProvider;
use App\Application\Ordering\OrderNumberGeneratorInterface;
use App\Application\Ordering\OrderPricer;
use App\Application\Ordering\OrderTransitions;
use App\Application\Ordering\Port\CartStorageInterface;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Application\Ordering\Port\ShippingMethodRepositoryInterface;
use App\Application\Ordering\PricedOrder;
use App\Application\Ordering\View\OrderView;
use App\Application\Payment\PaymentStarter;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Inventory\StockPolicy;
use App\Domain\Inventory\StockRequest;
use App\Domain\Ordering\OrderState;
use App\Domain\Payment\PaymentState;
use App\Domain\Shared\Quantity;
use App\Entity\Customer;
use App\Entity\Embeddable\PostalAddress;
use App\Entity\Order;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Checkout: the wizard's data, PlaceOrder and the confirmation page.
 */
final readonly class CheckoutHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private CurrentCustomerInterface $currentCustomer,
        private CartProvider $carts,
        private CartPresenter $cartPresenter,
        private CartStorageInterface $cartStorage,
        private OrderPricer $pricer,
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
        private ShippingMethodRepositoryInterface $shippingMethods,
        private CountryRepositoryInterface $countries,
        private AddressWriter $addressWriter,
        private StockPolicy $stockPolicy,
        private OrderNumberGeneratorInterface $orderNumbers,
        private PaymentStarter $paymentStarter,
        private OrderTransitions $orderTransitions,
        #[Target('order')]
        private WorkflowInterface $orderWorkflow,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function checkout(GetCheckout $query): CheckoutView
    {
        $store = $this->tenantContext->requireStore();
        $cart = $this->carts->current();
        $customer = $this->currentCustomer->get();
        $country = strtoupper($query->country ?? $customer?->getDefaultShippingAddress()?->getCountry()->getCode() ?? $store->getCountry()->getCode());

        $options = [];
        if (null !== $cart) {
            foreach ($this->pricer->shippingOptions($store, $cart, $this->shippingMethods->findActive(), $country) as $option) {
                $options[] = [
                    'code' => $option['method']->getCode(),
                    'name' => $option['method']->getName(),
                    'description' => $option['method']->getDescription(),
                    'net' => $option['net'],
                    'gross' => $option['gross'],
                    'totalGross' => $option['totalGross'],
                    'totalTax' => $option['totalTax'],
                ];
            }
        }

        return new CheckoutView(
            $this->cartPresenter->present($store, $cart),
            null === $customer ? null : [
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'addresses' => array_values(array_map(AddressView::from(...), $customer->getAddresses()->toArray())),
            ],
            array_map(static fn ($c) => ['code' => $c->getCode(), 'name' => $c->getName()], $this->countries->findAll()),
            $country,
            $options,
        );
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function placeOrder(PlaceOrder $command): PlacedOrderView
    {
        $store = $this->tenantContext->requireStore();
        $input = $command->input;
        $customer = $this->currentCustomer->get();
        $cart = $this->carts->current();
        if (null === $cart || 0 === $cart->getItems()->count()) {
            throw ValidationException::forField('cart', 'Your cart is empty.');
        }

        $email = $customer?->getEmail() ?? trim($input->email ?? '');
        if ('' === $email) {
            throw ValidationException::forField('email', 'Enter your email address.');
        }
        [$billing, $shipping] = $this->addresses($input, $customer);
        $method = $this->shippingMethods->findActiveByCode($input->shippingMethod)
            ?? throw ValidationException::forField('shippingMethod', 'Choose a shipping method.');

        $priced = $this->pricer->price($store, $cart, $method, $shipping->getCountryCode());
        $this->assertCanBePlaced($cart, $priced, $input);

        // Effects, in the same transaction as the transition (command bus: doctrine_transaction).
        foreach ($cart->getItems() as $item) {
            $variant = $item->getVariant() ?? throw new \LogicException('Checked above.');
            $variant->applyStock($variant->stock()->reserve(Quantity::of($item->getQuantity())));
            $item->snapshot($priced->lines[$variant->getPublicId()->toRfc4122()]);
        }
        $cart->snapshot(
            $this->orderNumbers->next($store),
            $email,
            $billing,
            $shipping,
            $method,
            $priced->shipping?->taxRate->toString() ?? '0.00',
            $store->getCountry()->getCode(),
            $priced->totals,
            $this->clock->now(),
        );
        $cart->getCoupon()?->recordUse();
        // The guards (OrderTransitionPolicy) check lines, addresses and shipping on the snapshot.
        $blockers = $this->orderWorkflow->buildTransitionBlockerList($cart, 'checkout');
        if (!$blockers->isEmpty()) {
            throw ValidationException::forField('cart', implode(' ', array_map(static fn ($b) => $b->getMessage(), iterator_to_array($blockers))));
        }
        $this->orderWorkflow->apply($cart, 'checkout');
        $this->orders->save($cart);

        $payment = $this->paymentStarter->start($cart);
        $orderId = $cart->getPublicId()->toRfc4122();
        $this->cartStorage->forgetCart((int) $store->getId());
        $this->cartStorage->rememberPlacedOrder($orderId);

        return new PlacedOrderView($orderId, (string) $cart->getOrderNumber(), (string) $payment->getCheckoutUrl());
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function confirmation(GetOrderConfirmation $query): OrderView
    {
        $order = $this->ownOrder($query->id);

        return OrderView::from($order, $this->payments->latestFor($order));
    }

    /**
     * "Try again" after a failed or abandoned payment: a new attempt, unless one is still open.
     */
    #[AsMessageHandler(bus: 'command.bus')]
    public function retryPayment(RetryPayment $command): string
    {
        $order = $this->ownOrder($command->id);
        if (OrderState::PaymentPending !== $order->state()) {
            throw new \DomainException('This order is not waiting for payment.');
        }
        $latest = $this->payments->latestFor($order);
        $payment = null !== $latest && PaymentState::Pending === $latest->state() && null !== $latest->getCheckoutUrl() ? $latest : $this->paymentStarter->start($order);

        return (string) $payment->getCheckoutUrl();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function cancel(CancelMyOrder $command): OrderView
    {
        $order = $this->ownOrder($command->id);
        $this->orderTransitions->apply($order, 'cancel', 'Cancelled by the customer');

        return OrderView::from($order, $this->payments->latestFor($order));
    }

    /** A placed order of the logged-in customer, or one this session placed as a guest. */
    private function ownOrder(string $id): Order
    {
        $order = Uuid::isValid($id) ? $this->orders->findByPublicId(Uuid::fromString($id)) : null;
        $customer = $this->currentCustomer->get();
        $allowed = null !== $order && !$order->isDraft()
            && ((null !== $customer && $order->getCustomer() === $customer) || $this->cartStorage->placedOrderIsKnown($id));

        return $allowed ? $order : throw NotFoundException::of('Order', $id);
    }

    /**
     * @return array{PostalAddress, PostalAddress}
     */
    private function addresses(CheckoutInput $input, ?Customer $customer): array
    {
        $billing = null !== $input->billingAddressId
            ? $this->bookAddress($customer, $input->billingAddressId, 'billingAddressId', billing: true)
            : $this->addressWriter->snapshot($input->billing ?? throw ValidationException::forField('billing', 'Enter the billing address.'), 'billing');

        if ($input->shippingSameAsBilling) {
            return [$billing, $billing];
        }
        $shipping = null !== $input->shippingAddressId
            ? $this->bookAddress($customer, $input->shippingAddressId, 'shippingAddressId', billing: false)
            : $this->addressWriter->snapshot($input->shipping ?? throw ValidationException::forField('shipping', 'Enter the delivery address.'), 'shipping');

        return [$billing, $shipping];
    }

    private function bookAddress(?Customer $customer, string $id, string $field, bool $billing): PostalAddress
    {
        $address = $customer?->findAddress($id);
        if (null === $address) {
            throw ValidationException::forField($field, 'Choose one of your addresses.');
        }
        if ($billing ? !$address->isUsableForBilling() : !$address->isUsableForShipping()) {
            throw ValidationException::forField($field, $billing ? 'This address is not set up for billing.' : 'This address is not set up for delivery.');
        }

        return $address->snapshot();
    }

    private function assertCanBePlaced(Order $cart, PricedOrder $priced, CheckoutInput $input): void
    {
        if ([] !== $priced->unavailable) {
            throw ValidationException::forField('cart', 'Some products in your cart are no longer available. Remove them to continue.');
        }
        if (null !== $priced->couponError) {
            throw ValidationException::forField('coupon', CouponMessages::for($priced->couponError));
        }
        if (null !== $priced->shippingError) {
            throw ValidationException::forField('shippingMethod', 'This shipping method does not deliver to the chosen country.');
        }

        $requests = [];
        foreach ($cart->getItems() as $item) {
            $variant = $item->getVariant() ?? throw new \LogicException('Unavailable lines are rejected above.');
            $requests[] = new StockRequest($variant->getSku(), $variant->stock(), Quantity::of($item->getQuantity()));
        }
        $shortages = $this->stockPolicy->shortages($requests);
        if ([] !== $shortages) {
            $parts = array_map(static fn ($s) => sprintf('%s (%d available)', $s->sku, $s->available), $shortages);
            throw ValidationException::forField('cart', 'Not enough stock for: '.implode(', ', $parts).'. Please change the quantity.');
        }

        if (null !== $input->expectedTotal && $input->expectedTotal !== $priced->totals->totalGross->amount) {
            throw ValidationException::forField('cart', 'The total of your order has changed. Please check the summary and confirm again.');
        }
    }
}
