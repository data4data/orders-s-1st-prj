<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Domain\Tenancy\StoreRole;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Tests\Support\AdminLogin;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class StoreSettingsTest extends WebTestCase
{
    use AdminLogin;
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const API = self::ADMIN.'/api/admin/settings';

    private KernelBrowser $client;
    private Store $auto;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->auto = ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class))['auto'];
    }

    public function testSettingsAreForManagersAndOwners(): void
    {
        $this->loginAdmin($this->auto, StoreRole::Staff);
        $this->api('GET', self::API, expected: 403);

        $this->loginAdmin($this->auto);
        $settings = $this->api('GET', self::API);
        self::assertSame(["MyOil's Auto", 'AUTO', 10], [$settings['profile']['name'], $settings['profile']['orderNumberPrefix'], $settings['profile']['lowStockThreshold']]);
        self::assertSame(['current' => 'fake', 'available' => ['fake']], $settings['gateway']);
        self::assertFalse($settings['canGrantOwner']);
    }

    public function testProfileAndBranding(): void
    {
        $this->loginAdmin($this->auto);
        $profile = ['name' => "MyOil's Auto NL", 'contactEmail' => 'shop@myoils-auto.test', 'logoUrl' => 'http://insecure.test/logo.png', 'faviconUrl' => null, 'primaryColor' => 'blue', 'accentColor' => '#F2A900', 'orderNumberPrefix' => 'AU', 'lowStockThreshold' => 5];
        $violations = self::violations($this->api('PUT', self::API.'/profile', $profile, 422));
        self::assertSame(['logoUrl', 'primaryColor'], array_keys($violations));

        $settings = $this->api('PUT', self::API.'/profile', ['logoUrl' => null, 'primaryColor' => '#123456'] + $profile);
        self::assertSame(["MyOil's Auto NL", '#123456', 'AU', 5], [$settings['profile']['name'], $settings['profile']['primaryColor'], $settings['profile']['orderNumberPrefix'], $settings['profile']['lowStockThreshold']]);

        // The shop header uses the new name and colour straight away.
        $this->client->request('GET', ShopFixtures::AUTO.'/about');
        self::assertSelectorTextContains('title', "MyOil's Auto NL");
        self::assertStringContainsString('#123456', (string) $this->client->getResponse()->getContent());
    }

    public function testDomains(): void
    {
        $this->loginAdmin($this->auto);
        self::assertSame('Enter a host name like shop.example.com.', self::violations($this->api('POST', self::API.'/domains', ['host' => 'not a host'], 422))['host']);
        self::assertSame('This host name is already used by a shop.', self::violations($this->api('POST', self::API.'/domains', ['host' => 'myoils-industrie.shop.test'], 422))['host']);

        $domains = $this->api('POST', self::API.'/domains', ['host' => 'Auto.MyOils.test'])['domains'];
        self::assertSame(['myoils-auto.shop.test', 'auto.myoils.test'], array_column($domains, 'host'));
        self::assertSame('The primary address cannot be removed. Make another address primary first.', $this->api('DELETE', self::API.'/domains/'.$domains[0]['id'], expected: 422)['detail']);

        $domains = $this->api('PUT', self::API.'/domains/'.$domains[1]['id'].'/primary')['domains'];
        self::assertSame([false, true], array_column($domains, 'isPrimary'));
        self::assertCount(1, $this->api('DELETE', self::API.'/domains/'.$domains[0]['id'])['domains']);

        // The new address opens the shop.
        $this->client->request('GET', 'https://auto.myoils.test/api/store');
        self::assertResponseIsSuccessful();
    }

    public function testShippingMethods(): void
    {
        $this->loginAdmin($this->auto);
        $method = ['code' => 'pickup', 'name' => 'Pick up in Utrecht', 'description' => 'Ready in 2 hours', 'calculator' => 'flat', 'amount' => '0', 'threshold' => null, 'brackets' => [], 'allowedCountries' => ['NL'], 'position' => 9, 'isActive' => true];
        self::assertSame('Another shipping method already uses this code.', self::violations($this->api('POST', self::API.'/shipping-methods', ['code' => 'standard'] + $method, 422))['code']);
        self::assertSame('Add at least one weight bracket.', self::violations($this->api('POST', self::API.'/shipping-methods', ['calculator' => 'weight_based'] + $method, 422))['brackets']);

        $settings = $this->api('POST', self::API.'/shipping-methods', $method);
        $pickup = array_values(array_filter($settings['shippingMethods'], static fn ($m) => 'pickup' === $m['code']))[0];
        self::assertSame(['0.00', ['NL']], [$pickup['amount'], $pickup['allowedCountries']]);

        $settings = $this->api('PUT', self::API.'/shipping-methods/'.$pickup['id'], ['calculator' => 'weight_based', 'brackets' => [['upToKg' => '5', 'amount' => '2.50'], ['upToKg' => null, 'amount' => '9']]] + $method);
        $pickup = array_values(array_filter($settings['shippingMethods'], static fn ($m) => 'pickup' === $m['code']))[0];
        self::assertSame([['upToKg' => '5', 'amount' => '2.50'], ['upToKg' => null, 'amount' => '9.00']], $pickup['brackets']);

        // The shop offers it at checkout.
        $this->client->request('GET', ShopFixtures::AUTO.'/api/products/synth-pro-5w-30');
        $variant = json_decode((string) $this->client->getResponse()->getContent(), true)['variants'][0]['publicId'];
        $this->api('POST', ShopFixtures::AUTO.'/api/cart/lines', ['variantId' => $variant, 'quantity' => 1]);
        $options = array_column($this->api('GET', ShopFixtures::AUTO.'/api/checkout')['shippingOptions'], 'gross', 'code');
        self::assertSame(303, $options['pickup']);
    }

    public function testGatewayAndNotifications(): void
    {
        $this->loginAdmin($this->auto);
        self::assertSame('This payment gateway is not installed.', self::violations($this->api('PUT', self::API.'/payment-gateway', ['code' => 'stripe'], 422))['code']);
        self::assertSame('fake', $this->api('PUT', self::API.'/payment-gateway', ['code' => 'fake'])['gateway']['current']);

        $notifications = $this->api('PUT', self::API.'/notifications', ['order_shipped' => false, 'unknown' => false])['notifications'];
        self::assertSame(['order_paid' => true, 'order_shipped' => false, 'order_cancelled' => true, 'order_refunded' => true, 'contact_message' => true], $notifications);

        $this->api('PUT', self::API.'/notifications', ['contact_message' => false]);
        $this->api('POST', ShopFixtures::AUTO.'/api/contact', ['name' => 'Kees', 'email' => 'kees@example.test', 'message' => 'No email for this one'], 204);
        self::assertQueuedEmailCount(0);
    }

    public function testStaffAndRoles(): void
    {
        $colleague = StaffUserFactory::createOne(['email' => 'colleague@myoils.test', 'firstName' => 'Anna', 'lastName' => 'Bos']);
        $manager = $this->loginAdmin($this->auto);

        self::assertSame('No staff user has this email. A super-admin creates staff users under Platform.', self::violations($this->api('POST', self::API.'/staff', ['email' => 'nobody@myoils.test', 'role' => 'staff'], 422))['email']);
        self::assertSame('Only an owner can add, remove or appoint owners.', $this->api('POST', self::API.'/staff', ['email' => 'colleague@myoils.test', 'role' => 'owner'], 422)['detail']);

        $staff = $this->api('POST', self::API.'/staff', ['email' => 'colleague@myoils.test', 'role' => 'staff'])['staff'];
        self::assertSame([$manager->getEmail(), 'colleague@myoils.test'], array_column($staff, 'email'));
        self::assertSame('This person already works in this shop.', self::violations($this->api('POST', self::API.'/staff', ['email' => 'colleague@myoils.test', 'role' => 'staff'], 422))['email']);

        $staff = $this->api('PUT', self::API.'/staff/'.$staff[1]['id'], ['role' => 'manager'])['staff'];
        self::assertSame('manager', $staff[1]['role']);

        // An owner can appoint owners, but the last owner cannot step down.
        $this->loginAdmin($this->auto, StoreRole::Owner);
        $settings = $this->api('GET', self::API);
        self::assertTrue($settings['canGrantOwner']);
        $owner = array_values(array_filter($settings['staff'], static fn ($m) => 'owner' === $m['role']))[0];
        self::assertSame('A shop needs at least one owner.', $this->api('PUT', self::API.'/staff/'.$owner['id'], ['role' => 'manager'], 422)['detail']);
        self::assertSame('A shop needs at least one owner.', $this->api('DELETE', self::API.'/staff/'.$owner['id'], expected: 422)['detail']);
        $staff = $this->api('DELETE', self::API.'/staff/'.$staff[1]['id'])['staff'];
        self::assertNotContains('colleague@myoils.test', array_column($staff, 'email'));
        self::assertSame('Anna', $colleague->getFirstName());
    }
}
