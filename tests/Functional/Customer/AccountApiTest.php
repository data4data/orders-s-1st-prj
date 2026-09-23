<?php

declare(strict_types=1);

namespace App\Tests\Functional\Customer;

use App\Entity\Customer;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AccountApiTest extends WebTestCase
{
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const API = ShopFixtures::AUTO.'/api/account';

    private KernelBrowser $client;
    private Customer $jan;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->jan = ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class))['jan'];
        $this->client->loginUser($this->jan, 'main');
    }

    public function testTheAccountOverview(): void
    {
        $account = $this->api('GET', self::API);

        self::assertSame(['jan@example.test', 'Jan', 'de Vries'], [$account['profile']['email'], $account['profile']['firstName'], $account['profile']['lastName']]);
        self::assertSame(['Damrak', 'Industrieweg'], array_column($account['addresses'], 'street'));
        self::assertSame(0, $account['orderCount']);
        self::assertSame(['BE', 'DE', 'NL'], array_column($account['countries'], 'code'));
    }

    public function testProfileAndPassword(): void
    {
        $this->api('PUT', self::API.'/profile', ['firstName' => 'Jan-Willem', 'lastName' => 'de Vries', 'phone' => '+31 6 1234 5678'], 204);
        self::assertSame('Jan-Willem', $this->api('GET', self::API)['profile']['firstName']);
        self::assertSame('Enter your first name.', self::violations($this->api('PUT', self::API.'/profile', ['firstName' => '', 'lastName' => 'x'], 422))['firstName']);

        self::assertSame('This is not your current password.', self::violations($this->api('PUT', self::API.'/password', ['currentPassword' => 'nope', 'newPassword' => 'long-enough-1'], 422))['currentPassword']);
        self::assertSame('Use at least 8 characters.', self::violations($this->api('PUT', self::API.'/password', ['currentPassword' => 'password', 'newPassword' => 'short'], 422))['newPassword']);
        $this->api('PUT', self::API.'/password', ['currentPassword' => 'password', 'newPassword' => 'long-enough-1'], 204);
    }

    public function testTheAddressBookKeepsABillingAndADeliveryAddress(): void
    {
        [$home, $garage] = $this->api('GET', self::API)['addresses'];

        // Garage is the only other delivery address, Home the only billing address.
        $problem = $this->api('DELETE', self::API.'/addresses/'.$home['id'], expected: 422);
        self::assertSame('At least one billing address is required.', $problem['detail']);
        $problem = $this->api('PUT', self::API.'/addresses/'.$home['id'], $this->address(['usableForBilling' => false]), 422);
        self::assertSame('At least one billing address is required.', $problem['detail']);

        // Deleting Garage is fine: Home can deliver too, and becomes the default delivery address.
        $addresses = $this->api('DELETE', self::API.'/addresses/'.$garage['id']);
        self::assertCount(1, $addresses);
        self::assertTrue($addresses[0]['isDefaultShipping']);

        // Now Home is the only delivery address as well.
        self::assertSame('At least one delivery address is required.', $this->api('PUT', self::API.'/addresses/'.$home['id'], $this->address(['usableForShipping' => false]), 422)['detail']);
    }

    public function testAddingAndEditingAddresses(): void
    {
        $violations = self::violations($this->api('POST', self::API.'/addresses', $this->address(['postcode' => 'X', 'vatId' => 'abc', 'usableForBilling' => false, 'usableForShipping' => false]), 422));
        self::assertSame('Enter a Dutch postcode like 1012 AB.', $violations['postcode']);
        self::assertSame('Enter a VAT number like NL123456789B01.', $violations['vatId']);
        self::assertSame('An address must be usable for billing, delivery or both.', $violations['usableForSomething']);
        self::assertSame('We do not deliver to this country.', self::violations($this->api('POST', self::API.'/addresses', $this->address(['countryCode' => 'FR']), 422))['countryCode']);

        $addresses = $this->api('POST', self::API.'/addresses', $this->address(['company' => 'De Vries B.V.', 'vatId' => 'NL812345678B01', 'postcode' => '2511aa']), 201);
        self::assertCount(3, $addresses);
        self::assertSame(['De Vries B.V.', '2511 AA'], [$addresses[2]['company'], $addresses[2]['postcode']]);

        $defaults = $this->api('PUT', self::API.'/addresses/defaults', ['billingId' => $addresses[2]['id'], 'shippingId' => $addresses[1]['id']]);
        self::assertSame([false, true, false, true, true, false], [$defaults[0]['isDefaultBilling'], $defaults[0]['isDefaultShipping'] || $defaults[1]['isDefaultShipping'], $defaults[1]['isDefaultBilling'], $defaults[1]['isDefaultShipping'], $defaults[2]['isDefaultBilling'], $defaults[2]['isDefaultShipping']]);
        self::assertSame('The default billing address must be usable for billing.', $this->api('PUT', self::API.'/addresses/defaults', ['billingId' => $addresses[1]['id'], 'shippingId' => $addresses[1]['id']], 422)['detail']);
    }

    public function testAnotherCustomersAddressAndOrdersAreNotVisible(): void
    {
        $other = self::getContainer()->get(CheckoutBuilder::class)->customer($this->jan->getStore() ?? throw new \LogicException(), CountryFactory::netherlands(), 'els@example.test', 'Els', 'Smit', ['street' => 'Kade', 'houseNumber' => '3', 'postcode' => '3011 AA', 'city' => 'Rotterdam']);
        $foreign = $other->getAddresses()->first();
        self::assertNotFalse($foreign);

        $this->api('PUT', self::API.'/addresses/'.$foreign->getPublicId()->toRfc4122(), $this->address(), 404);
        $this->api('GET', self::API.'/orders/01a0c816-77ce-7834-9be5-1543a99f5efe', expected: 404);
        self::assertSame(0, $this->api('GET', self::API.'/orders')['total']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function address(array $overrides = []): array
    {
        return $overrides + [
            'label' => 'Office', 'firstName' => 'Jan', 'lastName' => 'de Vries', 'company' => null, 'vatId' => null,
            'street' => 'Lange Voorhout', 'houseNumber' => '9', 'postcode' => '2514 EA', 'city' => 'Den Haag', 'countryCode' => 'NL',
            'phone' => null, 'usableForBilling' => true, 'usableForShipping' => true,
        ];
    }
}
