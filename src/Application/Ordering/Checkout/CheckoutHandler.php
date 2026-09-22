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
use App\Application\Ordering\Port\CartStorageInterface;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Application\Ordering\Port\ShippingMethodRepositoryInterface;
use App\Application\Ordering\PricedOrder;
use App\Application\Ordering\View\OrderView;
use App\Application\Payment\PaymentGatewayRegistry;
use App\Application\Payment\PaymentRequest;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Inventory\StockPolicy;
use App\Domain\Inventory\StockRequest;
use App\Domain\Money\Money;
use App\Domain\Shared\Quantity;
use App\Entity\Customer;
use App\Entity\Embeddable\PostalAddress;
use App\Entity\Order;
use App\Entity\Payment;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private PaymentGatewayRegistry $gateways,
        #[Target('order')]
        private WorkflowInterface $orderWorkflow,
        private UrlGeneratorInterface $urls,
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
        $this->orderWorkflow->apply($cart, 'checkout');
        $this->orders->save($cart);

        $payment = $this->startPayment($cart);
        $orderId = $cart->getPublicId()->toRfc4122();
        $this->cartStorage->forgetCart((int) $store->getId());
        $this->cartStorage->rememberPlacedOrder($orderId);

        return new PlacedOrderView($orderId, (string) $cart->getOrderNumber(), (string) $payment->getCheckoutUrl());
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function confirmation(GetOrderConfirmation $query): OrderView
    {
        $order = Uuid::isValid($query->id) ? $this->orders->findByPublicId(Uuid::fromString($query->id)) : null;
        $customer = $this->currentCustomer->get();
        $allowed = null !== $order && !$order->isDraft()
            && ((null !== $customer && $order->getCustomer() === $customer) || $this->cartStorage->placedOrderIsKnown($query->id));
        if (!$allowed) {
            throw NotFoundException::of('Order', $query->id);
        }

        return OrderView::from($order, $this->payments->latestFor($order));
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
        if (!$this->orderWorkflow->can($cart, 'checkout')) {
            throw ValidationException::forField('cart', 'This order cannot be placed.');
        }
    }

    private function startPayment(Order $order): Payment
    {
        $store = $this->tenantContext->requireStore();
        $gateway = $this->gateways->forStore($store);
        $payment = new Payment($order, $gateway->code(), $order->getTotalGross(), $order->getCurrencyCode());
        $this->payments->save($payment);

        $session = $gateway->createCheckoutSession(new PaymentRequest(
            $payment->getPublicId()->toRfc4122(),
            (string) $order->getOrderNumber(),
            Money::of($order->getTotalGross(), $order->getCurrencyCode()),
            (string) $order->getCustomerEmail(),
            $this->urls->generate('order_confirmation', ['id' => $order->getPublicId()->toRfc4122()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->urls->generate('payment_webhook', ['gateway' => $gateway->code()], UrlGeneratorInterface::ABSOLUTE_URL),
        ));
        $payment->attachSession($session->externalReference, $session->redirectUrl, $session->metadata);
        $this->payments->save($payment);

        return $payment;
    }
}
