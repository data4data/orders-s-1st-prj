<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ordering;

use App\Domain\Shared\Quantity;
use App\Entity\Coupon;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\ProductVariant;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CheckoutTest extends WebTestCase
{
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const AUTO = ShopFixtures::AUTO;

    private KernelBrowser $client;
    private Customer $jan;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->jan = ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class))['jan'];
    }

    public function testShippingOptionsDependOnTheCountry(): void
    {
        $this->addToCart('synth-pro-5w-30', '5 L', 2);

        $nl = array_column($this->api('GET', self::AUTO.'/api/checkout')['shippingOptions'], 'gross', 'code');
        self::assertSame(['standard' => 699, 'express' => 1499, 'europe' => null], $nl);

        $de = $this->api('GET', self::AUTO.'/api/checkout?country=DE');
        self::assertSame('DE', $de['country']);
        // 2 × 4.6 kg = 9.2 kg: first DHL bracket.
        self::assertSame(['standard' => null, 'express' => null, 'europe' => 1499], array_column($de['shippingOptions'], 'gross', 'code'));
        self::assertContains(['code' => 'DE', 'name' => 'Germany'], $de['countries']);
    }

    public function testAGuestPlacesAnOrder(): void
    {
        $this->addToCart('synth-pro-5w-30', '5 L', 2);
        $this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'WELCOME10']);

        $placed = $this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout(['expectedTotal' => 9689]), 201);

        self::assertSame('AUTO-000001', $placed['orderNumber']);
        self::assertStringContainsString('/fake-gateway/fake_', $placed['redirectUrl']);

        $order = $this->order($placed['orderId']);
        self::assertSame('payment_pending', $order->getState());
        self::assertSame('piet@example.test', $order->getCustomerEmail());
        self::assertNull($order->getCustomer());
        self::assertSame([8256, 826, 578, 8008, 1681, 9689], [$order->getItemsNet(), $order->getDiscountNet(), $order->getShippingNet(), $order->getTotalNet(), $order->getTotalTax(), $order->getTotalGross()]);
        self::assertSame('1012 LG', $order->getBillingAddress()->toArray()['postcode']);
        self::assertSame('Amsterdam', $order->getShippingAddress()->toArray()['city']);
        self::assertSame('PostNL Standard', $order->getShippingMethodName());
        self::assertSame('WELCOME10', $order->getCouponCode());

        $item = $order->getItems()->first();
        self::assertNotFalse($item);
        self::assertSame(['SP530-5', 4128, 826, '21.00', 7430, 1560, 8990], [$item->getSku(), $item->getUnitPriceNet(), $item->getDiscountNet(), $item->getTaxRate(), $item->getLineNet(), $item->getLineTax(), $item->getLineGross()]);

        self::assertSame(2, $this->entity(ProductVariant::class, ['sku' => 'SP530-5'])->getReserved());
        self::assertSame(1, $this->entity(Coupon::class, ['code' => 'WELCOME10'])->getTimesUsed());
        $payment = $this->entity(Payment::class, ['order' => $order]);
        self::assertSame(['pending', 9689, 'fake'], [$payment->getState(), $payment->getAmount(), $payment->getGatewayCode()]);

        // The cart is empty again; the confirmation is shown to this session only.
        self::assertSame(0, $this->api('GET', self::AUTO.'/api/cart')['itemCount']);
        $confirmation = $this->api('GET', self::AUTO.'/api/orders/'.$placed['orderId']);
        self::assertSame(['AUTO-000001', 'payment_pending', 'order.state.payment_pending', 'pending'], [$confirmation['number'], $confirmation['state'], $confirmation['stateLabel'], $confirmation['paymentState']]);
        self::assertSame($placed['redirectUrl'], $confirmation['paymentUrl']);

        $this->client->restart();
        $this->csrfToken = '';
        $this->api('GET', self::AUTO.'/api/orders/'.$placed['orderId'], expected: 404);
    }

    public function testValidationErrorsNameTheField(): void
    {
        $this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout(), 422);
        self::assertSame('Your cart is empty.', self::violations($this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout(), 422))['cart']);

        $this->addToCart('synth-pro-5w-30', '5 L', 1);
        $violations = self::violations($this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout([
            'email' => 'not-an-email',
            'billing' => ['firstName' => '', 'postcode' => '12'] + $this->address(),
            'acceptTerms' => false,
        ]), 422));
        self::assertSame('Enter a valid email address.', $violations['email']);
        self::assertSame('Enter the first name.', $violations['billing.firstName']);
        self::assertSame('Enter a Dutch postcode like 1012 AB.', $violations['billing.postcode']);
        self::assertSame('Please accept the terms and conditions.', $violations['acceptTerms']);

        $violations = self::violations($this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout(['shippingSameAsBilling' => false]), 422));
        self::assertSame('Enter the delivery address.', $violations['shipping']);

        // Express does not deliver to Belgium.
        $violations = self::violations($this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout([
            'shippingSameAsBilling' => false,
            'shipping' => ['countryCode' => 'BE', 'postcode' => '1000', 'city' => 'Brussel'] + $this->address(),
            'shippingMethod' => 'express',
        ]), 422));
        self::assertSame('This shipping method does not deliver to the chosen country.', $violations['shippingMethod']);
    }

    public function testNothingIsPlacedWhenStockRanOutOrTheTotalChanged(): void
    {
        $this->addToCart('synth-pro-5w-30', '208 L drum', 2);

        // Someone else reserved one drum in the meantime.
        $drum = $this->entity(ProductVariant::class, ['sku' => 'SP530-208']);
        $drum->applyStock($drum->stock()->reserve(Quantity::of(1)));
        $this->em()->flush();

        $violations = self::violations($this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout(['shippingMethod' => 'standard']), 422));
        self::assertSame('Not enough stock for: SP530-208 (1 available). Please change the quantity.', $violations['cart']);

        $this->api('PATCH', self::AUTO.'/api/cart/lines/'.$this->variantId('synth-pro-5w-30', '208 L drum'), ['quantity' => 1]);
        $violations = self::violations($this->api('POST', self::AUTO.'/api/checkout', $this->guestCheckout(['expectedTotal' => 100]), 422));
        self::assertSame('The total of your order has changed. Please check the summary and confirm again.', $violations['cart']);
        self::assertSame(1, $this->api('GET', self::AUTO.'/api/cart')['itemCount']);
    }

    public function testACustomerPaysWithAddressBookEntries(): void
    {
        $this->client->loginUser($this->jan, 'main');
        $this->addToCart('longlife-0w-20', '5 L', 3);
        $checkout = $this->api('GET', self::AUTO.'/api/checkout');
        self::assertSame('jan@example.test', $checkout['customer']['email']);
        $home = array_values(array_filter($checkout['customer']['addresses'], static fn ($a) => $a['isDefaultBilling']))[0]['id'];
        $garage = array_values(array_filter($checkout['customer']['addresses'], static fn ($a) => !$a['usableForBilling']))[0]['id'];

        $violations = self::violations($this->api('POST', self::AUTO.'/api/checkout', ['billingAddressId' => $garage, 'shippingMethod' => 'standard', 'acceptTerms' => true], 422));
        self::assertSame('This address is not set up for billing.', $violations['billingAddressId']);

        $placed = $this->api('POST', self::AUTO.'/api/checkout', [
            'billingAddressId' => $home,
            'shippingSameAsBilling' => false,
            'shippingAddressId' => $garage,
            'shippingMethod' => 'standard',
            'acceptTerms' => true,
        ], 201);

        $order = $this->order($placed['orderId']);
        self::assertSame('jan@example.test', $order->getCustomerEmail());
        self::assertSame($this->jan->getId(), $order->getCustomer()?->getId());
        self::assertSame('Damrak', $order->getBillingAddress()->toArray()['street']);
        self::assertSame('Utrecht', $order->getShippingAddress()->toArray()['city']);
        // 3 × €54.55 = €163.64 incl. VAT: free shipping.
        self::assertSame([0, 16364], [$order->getShippingNet(), $order->getTotalGross()]);

        $orders = $this->api('GET', self::AUTO.'/api/account/orders');
        self::assertSame(1, $orders['total']);
        self::assertSame($placed['orderNumber'], $orders['items'][0]['number']);
    }

    public function testCartOfAGuestJoinsTheAccountOnLogin(): void
    {
        $this->client->loginUser($this->jan, 'main');
        $this->addToCart('synth-pro-5w-30', '1 L', 1);
        $this->client->request('POST', self::AUTO.'/logout', ['_csrf_token' => $this->logoutToken()]);
        self::assertResponseRedirects();

        $this->addToCart('synth-pro-5w-30', '1 L', 2);
        $this->addToCart('longlife-0w-20', '1 L', 1);

        $this->client->request('GET', self::AUTO.'/login');
        $this->client->submitForm('Log in', ['email' => 'jan@example.test', 'password' => 'password']);
        self::assertResponseRedirects('/account');

        $cart = $this->api('GET', self::AUTO.'/api/cart');
        self::assertSame(4, $cart['itemCount']);
        self::assertSame([3, 1], array_column($cart['lines'], 'quantity'));
    }

    private function addToCart(string $slug, string $pack, int $quantity): void
    {
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variantId($slug, $pack), 'quantity' => $quantity]);
    }

    private function variantId(string $slug, string $pack): string
    {
        $this->client->request('GET', self::AUTO.'/api/products/'.$slug);
        foreach (json_decode((string) $this->client->getResponse()->getContent(), true)['variants'] as $variant) {
            if ($variant['name'] === $pack) {
                return $variant['publicId'];
            }
        }
        self::fail('Unknown pack '.$pack);
    }

    /**
     * @return array<string, string>
     */
    private function address(): array
    {
        return ['firstName' => 'Piet', 'lastName' => 'Jansen', 'street' => 'Damrak', 'houseNumber' => '1', 'postcode' => '1012lg', 'city' => 'Amsterdam', 'countryCode' => 'NL'];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function guestCheckout(array $overrides = []): array
    {
        return $overrides + [
            'email' => 'piet@example.test',
            'billing' => $this->address(),
            'shippingSameAsBilling' => true,
            'shippingMethod' => 'standard',
            'acceptTerms' => true,
        ];
    }

    /** The account page hands the logout CSRF token to its Vue island. */
    private function logoutToken(): string
    {
        $this->client->request('GET', self::AUTO.'/account');

        return json_decode((string) $this->client->getCrawler()->filter('[data-vue-page="Account"]')->attr('data-props'), true)['logoutToken'];
    }

    private function order(string $publicId): Order
    {
        $this->em()->clear();

        return $this->entity(Order::class, ['publicId' => Uuid::fromString($publicId)]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $criteria
     *
     * @return T
     */
    private function entity(string $class, array $criteria): object
    {
        // The test reads across stores, like a super-admin would.
        if ($this->em()->getFilters()->isEnabled('tenant')) {
            $this->em()->getFilters()->disable('tenant');
        }
        $entity = $this->em()->getRepository($class)->findOneBy($criteria);
        self::assertNotNull($entity, $class.' not found');

        return $entity;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
