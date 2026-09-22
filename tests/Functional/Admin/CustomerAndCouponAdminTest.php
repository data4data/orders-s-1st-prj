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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CustomerAndCouponAdminTest extends WebTestCase
{
    use AdminLogin;
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private KernelBrowser $client;
    /** @var array{auto: Store, industrie: Store, jan: \App\Entity\Customer} */
    private array $shops;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->shops = ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class));
    }

    public function testCustomersOfTheSelectedStoreOnly(): void
    {
        $this->loginAdmin($this->shops['auto'], StoreRole::Staff);

        $list = $this->api('GET', self::ADMIN.'/api/admin/customers');
        self::assertSame(1, $list['total']);
        self::assertSame(['Jan de Vries', 'jan@example.test', 0, 0], [$list['items'][0]['name'], $list['items'][0]['email'], $list['items'][0]['orders'], $list['items'][0]['spent']]);
        self::assertSame(0, $this->api('GET', self::ADMIN.'/api/admin/customers?q=nobody')['total']);

        $detail = $this->api('GET', self::ADMIN.'/api/admin/customers/'.$list['items'][0]['id']);
        self::assertSame(['Damrak', 'Industrieweg'], array_column($detail['addresses'], 'street'));

        $this->loginAdmin($this->shops['industrie']);
        self::assertSame(0, $this->api('GET', self::ADMIN.'/api/admin/customers')['total']);
        $this->api('GET', self::ADMIN.'/api/admin/customers/'.$list['items'][0]['id'], expected: 404);
    }

    public function testContactMessagesTab(): void
    {
        $this->api('POST', ShopFixtures::AUTO.'/api/contact', ['name' => 'Kees', 'email' => 'kees@example.test', 'subject' => 'business', 'message' => 'Volume prices for drums?'], 204);
        $this->loginAdmin($this->shops['auto'], StoreRole::Staff);

        $messages = $this->api('GET', self::ADMIN.'/api/admin/contact-messages');
        self::assertSame([1, 1, false], [$messages['total'], $messages['unread'], $messages['items'][0]['read']]);
        $this->api('POST', self::ADMIN.'/api/admin/contact-messages/'.$messages['items'][0]['id'].'/read', expected: 204);
        self::assertSame(0, $this->api('GET', self::ADMIN.'/api/admin/contact-messages?unread=1')['total']);
    }

    public function testCouponsAreManagedByManagers(): void
    {
        $this->loginAdmin($this->shops['auto'], StoreRole::Staff);
        self::assertSame(['FIVEOFF', 'SUMMER2025', 'WELCOME10'], array_column($this->api('GET', self::ADMIN.'/api/admin/coupons'), 'code'));
        $this->api('POST', self::ADMIN.'/api/admin/coupons', ['code' => 'NEW', 'type' => 'fixed', 'amount' => '5'], 403);

        $this->loginAdmin($this->shops['auto']);
        $violations = self::violations($this->api('POST', self::ADMIN.'/api/admin/coupons', ['code' => 'welcome10', 'type' => 'percentage', 'percent' => '150', 'validFrom' => '2026-12-31', 'validTo' => '2026-01-01'], 422));
        self::assertSame('Enter a percentage between 0 and 100, e.g. 10 or 12.5.', $violations['percent']);
        self::assertSame('The end date must be after the start date.', $violations['periodValid']);
        self::assertSame('Another coupon already uses this code.', self::violations($this->api('POST', self::ADMIN.'/api/admin/coupons', ['code' => 'welcome10', 'type' => 'percentage', 'percent' => '15'], 422))['code']);

        $created = $this->api('POST', self::ADMIN.'/api/admin/coupons', ['code' => 'drums-25', 'type' => 'fixed', 'amount' => '25.00', 'minOrderNet' => '500', 'usageLimit' => 10], 201);
        $coupon = array_values(array_filter($this->api('GET', self::ADMIN.'/api/admin/coupons'), static fn ($c) => 'DRUMS-25' === $c['code']))[0];
        self::assertSame(['fixed', '25.00', '500.00', 10, true], [$coupon['type'], $coupon['amount'], $coupon['minOrderNet'], $coupon['usageLimit'], $coupon['isActive']]);

        $this->api('PUT', self::ADMIN.'/api/admin/coupons/'.$created['id'], ['code' => 'drums-25', 'type' => 'fixed', 'amount' => '25.00', 'isActive' => false]);
        $cart = $this->client;
        // A switched-off coupon is refused in the shop.
        $this->client->request('GET', ShopFixtures::AUTO.'/api/products/synth-pro-5w-30');
        $variant = json_decode((string) $cart->getResponse()->getContent(), true)['variants'][3]['publicId'];
        $this->api('POST', ShopFixtures::AUTO.'/api/cart/lines', ['variantId' => $variant, 'quantity' => 1]);
        self::assertSame('This code is not valid.', self::violations($this->api('POST', ShopFixtures::AUTO.'/api/cart/coupon', ['code' => 'DRUMS-25'], 422))['code']);
    }
}
