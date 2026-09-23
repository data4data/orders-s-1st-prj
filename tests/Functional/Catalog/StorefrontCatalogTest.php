<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Tests\Support\CatalogFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class StorefrontCatalogTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const AUTO = 'https://myoils-auto.shop.test';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        CatalogFixtures::twoShops(self::getContainer()->get(CatalogBuilder::class));
    }

    public function testCategoryTreeOfTheStore(): void
    {
        $tree = $this->get('/api/categories');

        self::assertSame(['Engine oil', 'Gear & ATF', 'Coolants'], array_column($tree, 'name'));
        self::assertSame(['Passenger car', 'Van & SUV', 'Classic cars'], array_column($tree[0]['children'], 'name'));
    }

    public function testACategoryListsItsProductsAndSubcategoriesWithGrossPrices(): void
    {
        $list = $this->get('/api/products?category=engine-oil&sort=price_asc');

        self::assertSame(6, $list['total']);
        self::assertSame(['Engine oil'], array_column($list['breadcrumbs'], 'name'));
        $first = $list['items'][0];
        self::assertSame("MyOil's Power 10W-40", $first['name']);
        self::assertSame(['currency' => 'EUR', 'net' => 740, 'gross' => 895, 'perLitre' => 895, 'vatRate' => '21.00'], $first['fromPrice']);
    }

    public function testFiltersCombineAndFacetsCount(): void
    {
        $list = $this->get('/api/products?filters[sae_viscosity][]=5W-30&packs[]=5000&inStock=1');

        self::assertSame(3, $list['total']);
        $sae = array_values(array_filter($list['facets'], static fn ($f) => 'sae_viscosity' === $f['code']))[0];
        self::assertContains(['value' => '5W-30', 'count' => 3, 'selected' => true], $sae['options']);
        self::assertSame(['5 L'], array_column(array_filter($list['packSizes'], static fn ($p) => $p['selected']), 'label'));
    }

    public function testPriceFilterUsesGrossPricesAndShowsTheMatchingPack(): void
    {
        $list = $this->get('/api/products?minPrice=4000&maxPrice=5000&sort=price_desc');

        self::assertSame(["MyOil's Synth Pro 5W-30", "MyOil's Classic 20W-50", "MyOil's Eco Drive 5W-30"], array_column($list['items'], 'name'));
        self::assertSame(4995, $list['items'][0]['fromPrice']['gross']);
        self::assertSame('5 L', $list['items'][0]['fromPackName']);
    }

    public function testSearchByNameOrSku(): void
    {
        self::assertSame(["MyOil's Coolant G12++"], array_column($this->get('/api/products?q=G12-20')['items'], 'name'));
    }

    public function testProductDetailWithPacksSpecsAndStock(): void
    {
        $product = $this->get('/api/products/synth-pro-5w-30');

        self::assertSame(['1 L', '5 L', '20 L', '208 L drum'], array_column($product['variants'], 'name'));
        self::assertSame([1295, 4995, 16900, 148900], array_map(static fn ($v) => $v['price']['gross'], $product['variants']));
        self::assertSame([1295, 999, 845, 716], array_map(static fn ($v) => $v['price']['perLitre'], $product['variants']));
        self::assertSame('low_stock', $product['variants'][3]['stock']);
        self::assertContains(['name' => 'OEM approvals', 'value' => 'VW 504.00/507.00, MB 229.51'], $product['specs']);
        self::assertSame(['Engine oil', 'Passenger car'], array_column($product['breadcrumbs'], 'name'));
        self::assertCount(2, $product['documents']);
    }

    public function testAnotherStoresProductIsNotFound(): void
    {
        $this->client->request('GET', 'https://myoils-industrie.shop.test/api/products/synth-pro-5w-30');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testUnknownCategoryIsNotFound(): void
    {
        $this->client->request('GET', self::AUTO.'/api/products?category=nope');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCatalogAndProductPagesRenderWithHeaderCategories(): void
    {
        $this->client->request('GET', self::AUTO.'/c/engine-oil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Engine oil');
        self::assertStringContainsString('Gear & ATF', (string) $this->client->getCrawler()->filter('[data-vue-layout="StorefrontHeader"]')->attr('data-props'));

        $this->client->request('GET', self::AUTO.'/p/synth-pro-5w-30');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "MyOil's Synth Pro 5W-30");

        $this->client->request('GET', self::AUTO.'/p/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<mixed>
     */
    private function get(string $path): array
    {
        $this->client->request('GET', self::AUTO.$path);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
