<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ordering;

use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\CheckoutBuilder;
use App\Tests\Support\JsonApi;
use App\Tests\Support\ShopFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CartApiTest extends WebTestCase
{
    use Factories;
    use JsonApi;
    use ResetDatabase;

    private const AUTO = ShopFixtures::AUTO;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        ShopFixtures::create(self::getContainer()->get(CatalogBuilder::class), self::getContainer()->get(CheckoutBuilder::class));
    }

    public function testAnEmptyCart(): void
    {
        $cart = $this->api('GET', self::AUTO.'/api/cart');

        self::assertSame(0, $cart['itemCount']);
        self::assertFalse($cart['canCheckout']);
    }

    public function testAddingLinesWithLivePricesAndShippingEstimate(): void
    {
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '5 L'), 'quantity' => 1]);
        $cart = $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '5 L'), 'quantity' => 1]);

        self::assertSame(2, $cart['itemCount']);
        self::assertCount(1, $cart['lines']);
        self::assertSame(['quantity' => 2, 'unitGross' => 4995, 'lineGross' => 9990], array_intersect_key($cart['lines'][0], ['quantity' => 0, 'unitGross' => 0, 'lineGross' => 0]));
        // €99.90 is below the €100 free-shipping threshold: PostNL Standard €6.99 is the cheapest.
        self::assertSame(['code' => 'standard', 'name' => 'PostNL Standard', 'gross' => 699], $cart['shippingEstimate']);
        self::assertSame(10689, $cart['totals']['totalGross']);
        self::assertTrue($cart['canCheckout']);

        // One more litre crosses €100: shipping becomes free.
        $cart = $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '1 L'), 'quantity' => 1]);
        self::assertSame(0, $cart['totals']['shippingGross']);
        self::assertSame(11285, $cart['totals']['totalGross']);
    }

    public function testQuantityAndStockLimits(): void
    {
        $drum = $this->variant('synth-pro-5w-30', '208 L drum');
        $violations = self::violations($this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $drum, 'quantity' => 3], 422));
        self::assertSame('Only 2 available.', $violations['quantity']);

        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $drum, 'quantity' => 2]);
        self::assertSame('Only 2 available.', self::violations($this->api('PATCH', self::AUTO.'/api/cart/lines/'.$drum, ['quantity' => 3], 422))['quantity']);
        self::assertSame('Choose a quantity between 1 and 99.', self::violations($this->api('PATCH', self::AUTO.'/api/cart/lines/'.$drum, ['quantity' => 0], 422))['quantity']);

        $soldOut = $this->variant('power-10w-40', '60 L');
        self::assertSame('This pack size is out of stock.', self::violations($this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $soldOut, 'quantity' => 1], 422))['quantity']);
    }

    public function testUpdatingAndRemovingLines(): void
    {
        $id = $this->variant('longlife-0w-20', '1 L');
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $id, 'quantity' => 1]);

        self::assertSame(4, $this->api('PATCH', self::AUTO.'/api/cart/lines/'.$id, ['quantity' => 4])['itemCount']);
        self::assertSame(0, $this->api('DELETE', self::AUTO.'/api/cart/lines/'.$id)['itemCount']);
        // Removing twice is fine (second tab, double click).
        self::assertSame(0, $this->api('DELETE', self::AUTO.'/api/cart/lines/'.$id)['itemCount']);
    }

    public function testCoupons(): void
    {
        self::assertSame('Add products to your cart first.', self::violations($this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'WELCOME10'], 422))['code']);

        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '1 L'), 'quantity' => 1]);
        self::assertSame('Your order is below the minimum amount for this code.', self::violations($this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'welcome10'], 422))['code']);
        self::assertSame('This code has expired.', self::violations($this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'SUMMER2025'], 422))['code']);
        self::assertSame('This code is not valid.', self::violations($this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'NOPE'], 422))['code']);

        $this->api('PATCH', self::AUTO.'/api/cart/lines/'.$this->variant('synth-pro-5w-30', '1 L'), ['quantity' => 3]);
        $cart = $this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'welcome10']);
        self::assertSame(['code' => 'WELCOME10', 'error' => null], $cart['coupon']);
        // VAT per line on the line net (decision #58): 3 × 10.70 = 32.10 net → €38.84 (not 3 × €12.95).
        // 10% of 32.10 = 3.21 net off → €34.96 gross, so €3.88 gross discount.
        self::assertSame(3884, $cart['totals']['itemsGross']);
        self::assertSame(321, $cart['totals']['discountNet']);
        self::assertSame(388, $cart['totals']['discountGross']);

        self::assertNull($this->api('DELETE', self::AUTO.'/api/cart/coupon')['coupon']);
    }

    public function testACouponThatStopsApplyingShowsWhyInTheCart(): void
    {
        $id = $this->variant('synth-pro-5w-30', '5 L');
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $id, 'quantity' => 1]);
        $this->api('POST', self::AUTO.'/api/cart/coupon', ['code' => 'WELCOME10']);

        $this->api('DELETE', self::AUTO.'/api/cart/lines/'.$id);
        $cart = $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '1 L'), 'quantity' => 1]);

        self::assertSame(['code' => 'WELCOME10', 'error' => 'below_minimum_order'], $cart['coupon']);
        self::assertSame(0, $cart['totals']['discountGross']);
    }

    public function testEachVisitorAndEachShopHasItsOwnCart(): void
    {
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '5 L'), 'quantity' => 1]);
        self::assertSame(0, $this->api('GET', ShopFixtures::INDUSTRIE.'/api/cart')['itemCount']);

        // A product of another shop cannot be added.
        $this->api('POST', ShopFixtures::INDUSTRIE.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '5 L'), 'quantity' => 1], 404);

        $this->client->restart();
        $this->csrfToken = '';
        self::assertSame(0, $this->api('GET', self::AUTO.'/api/cart')['itemCount']);
    }

    public function testTheHeaderShowsTheCartCount(): void
    {
        $this->api('POST', self::AUTO.'/api/cart/lines', ['variantId' => $this->variant('synth-pro-5w-30', '5 L'), 'quantity' => 3]);

        $this->client->request('GET', self::AUTO.'/cart');
        self::assertResponseIsSuccessful();
        $layout = json_decode((string) $this->client->getCrawler()->filter('[data-vue-layout="StorefrontHeader"]')->attr('data-props'), true);
        self::assertSame(3, $layout['layout']['cartItemCount']);
    }

    private function variant(string $slug, string $pack): string
    {
        $this->client->request('GET', self::AUTO.'/api/products/'.$slug);
        $product = json_decode((string) $this->client->getResponse()->getContent(), true);
        foreach ($product['variants'] as $variant) {
            if ($variant['name'] === $pack) {
                return $variant['publicId'];
            }
        }
        self::fail(sprintf('No %s pack for %s.', $pack, $slug));
    }
}
