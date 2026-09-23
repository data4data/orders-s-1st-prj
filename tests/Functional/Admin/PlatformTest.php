<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Domain\Tenancy\StoreRole;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Tests\Support\AdminLogin;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PlatformTest extends WebTestCase
{
    use AdminLogin;
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const API = self::ADMIN.'/api/admin/platform';

    private KernelBrowser $client;
    private Store $auto;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->auto = ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class))['auto'];
    }

    public function testOnlySuperAdmins(): void
    {
        $this->loginAdmin($this->auto, StoreRole::Owner);
        $this->api('GET', self::API, expected: 403);

        $this->loginAdmin(null, null, superAdmin: true);
        $overview = $this->api('GET', self::API);
        self::assertSame(["MyOil's Auto", "MyOil's Industrie"], array_column($overview['stores'], 'name'));
        self::assertSame(['BE', 'DE', 'NL'], array_column($overview['countries'], 'code'));
        self::assertSame(['reduced', 'standard', 'zero'], array_column($overview['taxCategories'], 'code'));
    }

    public function testCreatingAndSwitchingOffAStore(): void
    {
        $this->loginAdmin(null, null, superAdmin: true);
        $violations = self::violations($this->api('POST', self::API.'/stores', ['code' => 'myoils-auto', 'name' => 'Dup', 'countryCode' => 'NL', 'currencyCode' => 'EUR', 'orderNumberPrefix' => 'X1', 'host' => 'myoils-auto.shop.test'], 422));
        self::assertSame(['orderNumberPrefix'], array_keys($violations));
        self::assertSame('Another shop already uses this code.', self::violations($this->api('POST', self::API.'/stores', ['code' => 'myoils-auto', 'name' => 'Dup', 'countryCode' => 'NL', 'currencyCode' => 'EUR', 'orderNumberPrefix' => 'DUP', 'host' => 'new.shop.test'], 422))['code']);

        $overview = $this->api('POST', self::API.'/stores', ['code' => 'myoils-marine', 'name' => "MyOil's Marine", 'countryCode' => 'NL', 'currencyCode' => 'EUR', 'orderNumberPrefix' => 'mar', 'host' => 'myoils-marine.shop.test'], 201);
        $marine = array_values(array_filter($overview['stores'], static fn ($s) => 'myoils-marine' === $s['code']))[0];
        self::assertSame(['MAR', true, [['host' => 'myoils-marine.shop.test', 'isPrimary' => true]]], [$marine['orderNumberPrefix'], $marine['isActive'], $marine['domains']]);

        $this->client->request('GET', 'https://myoils-marine.shop.test/api/store');
        self::assertResponseIsSuccessful();

        $this->api('PUT', self::API.'/stores/'.$marine['id'].'/active', ['active' => false]);
        $this->client->request('GET', 'https://myoils-marine.shop.test/api/store');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCountriesAndVatRates(): void
    {
        $this->loginAdmin(null, null, superAdmin: true);
        $overview = $this->api('POST', self::API.'/countries', ['code' => 'fr', 'name' => 'France', 'isEu' => true], 201);
        self::assertContains('FR', array_column($overview['countries'], 'code'));
        self::assertSame('This country already exists.', self::violations($this->api('POST', self::API.'/countries', ['code' => 'FR', 'name' => 'France'], 422))['code']);

        // NL standard 21 % runs open-ended from 2012: a new period overlaps it.
        self::assertSame('This period overlaps an existing rate. End the current rate first (set its last day).', self::violations($this->api('POST', self::API.'/tax-rates', ['countryCode' => 'NL', 'taxCategory' => 'standard', 'rate' => '22', 'validFrom' => '2027-01-01'], 422))['validFrom']);

        $overview = $this->api('POST', self::API.'/tax-rates', ['countryCode' => 'FR', 'taxCategory' => 'standard', 'rate' => '20', 'validFrom' => '2014-01-01'], 201);
        $france = array_values(array_filter($overview['countries'], static fn ($c) => 'FR' === $c['code']))[0];
        self::assertSame([['taxCategory' => 'standard', 'rate' => '20.00', 'validFrom' => '2014-01-01', 'validTo' => null]], array_map(static fn ($r) => array_diff_key($r, ['id' => 0]), $france['rates']));

        self::assertSame('Use lower-case letters and _, e.g. reduced.', self::violations($this->api('POST', self::API.'/tax-categories', ['code' => 'Super Reduced!', 'name' => 'x'], 422))['code']);
        self::assertContains('super_reduced', array_column($this->api('POST', self::API.'/tax-categories', ['code' => 'super_reduced', 'name' => 'Super reduced'], 201)['taxCategories'], 'code'));
    }

    public function testStaffUsers(): void
    {
        $me = $this->loginAdmin(null, null, superAdmin: true);
        $overview = $this->api('POST', self::API.'/staff-users', ['email' => 'new@myoils.test', 'firstName' => 'Nina', 'lastName' => 'Kok', 'password' => 'long-password', 'superAdmin' => false], 201);
        $nina = array_values(array_filter($overview['staffUsers'], static fn ($u) => 'new@myoils.test' === $u['email']))[0];
        self::assertSame('A staff user with this email already exists.', self::violations($this->api('POST', self::API.'/staff-users', ['email' => 'new@myoils.test', 'firstName' => 'N', 'lastName' => 'K', 'password' => 'long-password'], 422))['email']);

        $overview = $this->api('PUT', self::API.'/staff-users/'.$nina['id'].'/super-admin', ['superAdmin' => true]);
        self::assertTrue(array_values(array_filter($overview['staffUsers'], static fn ($u) => 'new@myoils.test' === $u['email']))[0]['superAdmin']);
        self::assertSame('You cannot remove your own super-admin rights.', $this->api('PUT', self::API.'/staff-users/'.$me->getId().'/super-admin', ['superAdmin' => false], 422)['detail']);

        // Nina can log in on the admin.
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', self::ADMIN.'/login');
        $this->client->submitForm('Log in', ['_username' => 'new@myoils.test', '_password' => 'long-password']);
        self::assertResponseRedirects();
    }

    public function testSystemShowsQueuesAndFailedJobs(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $connection->insert('messenger_messages', ['body' => 'x', 'headers' => json_encode(['type' => 'App\\Application\\Payment\\ProcessPaymentWebhook', 'X-Message-Stamp-Symfony\\Component\\Messenger\\Stamp\\ErrorDetailsStamp' => '[{"exceptionMessage":"Payment not found"}]']), 'queue_name' => 'failed', 'created_at' => '2026-09-22 10:00:00', 'available_at' => '2026-09-22 10:00:00']);
        $this->loginAdmin(null, null, superAdmin: true);

        $system = $this->api('GET', self::API.'/system');
        self::assertSame(1, array_column($system['queues'], 'waiting', 'queue')['failed']);
        self::assertSame('App\\Application\\Payment\\ProcessPaymentWebhook', $system['failed'][0]['message']);
        self::assertStringContainsString('Payment not found', $system['failed'][0]['error']);

        $system = $this->api('POST', self::API.'/system/failed/'.$system['failed'][0]['id'].'/retry');
        self::assertSame([], $system['failed']);
        self::assertSame(1, array_column($system['queues'], 'waiting', 'queue')['async']);
        $this->api('DELETE', self::API.'/system/failed/999999', expected: 404);
    }
}
