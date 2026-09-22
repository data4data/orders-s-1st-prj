<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Domain\Tenancy\StoreRole;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\Factory\StaffUserFactory;
use App\Infrastructure\Fixtures\Factory\StoreMembershipFactory;
use App\Tests\Support\CatalogFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AdminCatalogTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const ADMIN = 'https://admin.shop.test';

    private KernelBrowser $client;
    /** @var array{auto: Store, industrie: Store} */
    private array $stores;
    private string $token = '';

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->stores = CatalogFixtures::twoShops(self::getContainer()->get(CatalogBuilder::class));
    }

    public function testStaffNeedAStoreRoleForTheSelectedStore(): void
    {
        $this->loginAs(StaffUserFactory::createOne());

        $this->client->request('GET', self::ADMIN.'/api/admin/catalog/products');

        self::assertResponseStatusCodeSame(403);
    }

    public function testProductListAndEditForm(): void
    {
        $this->loginInStore('auto');

        $list = $this->api('GET', '/api/admin/catalog/products?q=synth');
        self::assertSame(1, $list['total']);
        $form = $this->api('GET', '/api/admin/catalog/products/'.$list['items'][0]['publicId']);

        self::assertSame('21.00', $form['vatRate']);
        self::assertSame(['10.70', '41.28', '139.67', '1230.58'], array_column($form['variants'], 'priceNet'));
        self::assertSame(1, $form['version']);
    }

    public function testSavingValidatesNestedFieldsAndUniqueness(): void
    {
        $this->loginInStore('auto');
        $form = $this->productForm('synth');

        $form['variants'][2]['priceNet'] = '12,50';
        $form['images'] = [['url' => 'http://example.com/a.png', 'alt' => '', 'variantSku' => null]];
        $violations = $this->violations($this->api('PUT', '/api/admin/catalog/products/'.$form['publicId'], $form, 422));
        self::assertSame('Enter an amount like 41.28 (net, without VAT).', $violations['variants[2].priceNet']);
        self::assertSame('Enter a full address starting with https://.', $violations['images[0].url']);
        self::assertArrayHasKey('images[0].alt', $violations);

        $form = $this->productForm('synth');
        $form['variants'][1]['sku'] = 'LL020-1'; // belongs to another product
        $form['slug'] = 'longlife-0w-20';
        $violations = $this->violations($this->api('PUT', '/api/admin/catalog/products/'.$form['publicId'], $form, 422));
        self::assertSame('Another product already uses this SKU.', $violations['variants[1].sku']);
        self::assertSame('Another product already uses this URL name.', $violations['slug']);
    }

    public function testSavingUpdatesTheShopAndAStaleVersionConflicts(): void
    {
        $this->loginInStore('auto');
        $form = $this->productForm('synth');
        $form['variants'][1]['priceNet'] = '41.29';

        $this->api('PUT', '/api/admin/catalog/products/'.$form['publicId'], $form);
        $this->api('PUT', '/api/admin/catalog/products/'.$form['publicId'], $form, 409);

        $this->client->request('GET', 'https://myoils-auto.shop.test/api/products/synth-pro-5w-30');
        $product = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(4996, $product['variants'][1]['price']['gross']);
    }

    public function testCreatingAProduct(): void
    {
        $this->loginInStore('auto');
        $taxCategory = $this->api('GET', '/api/admin/catalog/tax-categories')[0]['id'];
        $category = array_values(array_filter($this->api('GET', '/api/admin/catalog/categories'), static fn ($c) => 'coolants' === $c['slug']))[0]['id'];

        $created = $this->api('POST', '/api/admin/catalog/products', [
            'name' => "MyOil's Brake Fluid DOT 4", 'slug' => 'brake-fluid-dot-4', 'brand' => "MyOil's", 'taxCategoryId' => $taxCategory, 'categoryIds' => [$category],
            'variants' => [['sku' => 'DOT4-1', 'name' => '1 L', 'volumeMl' => 1000, 'weightG' => 1100, 'priceNet' => '8.26', 'onHand' => 30]],
            'images' => [['url' => 'https://placehold.co/600x600/png', 'alt' => 'Brake fluid', 'variantSku' => 'DOT4-1']],
        ], 201);

        self::assertNotEmpty($created['publicId']);
        $this->client->request('GET', 'https://myoils-auto.shop.test/api/products/brake-fluid-dot-4');
        self::assertSame(999, json_decode((string) $this->client->getResponse()->getContent(), true)['variants'][0]['price']['gross']);
    }

    public function testCategoryRules(): void
    {
        $this->loginInStore('auto');
        $tree = $this->api('GET', '/api/admin/catalog/categories');
        $engineOil = $tree[0];

        $this->api('DELETE', '/api/admin/catalog/categories/'.$engineOil['id'], null, 422); // has sub-categories
        $coolants = array_values(array_filter($tree, static fn ($c) => 'coolants' === $c['slug']))[0];
        $problem = $this->api('DELETE', '/api/admin/catalog/categories/'.$coolants['id'], null, 422); // has products
        self::assertStringContainsString('still contains 1 product(s)', $problem['detail']);

        $violations = $this->violations($this->api('POST', '/api/admin/catalog/categories', ['name' => 'Dup', 'slug' => 'coolants'], 422));
        self::assertSame('Another category already uses this URL name.', $violations['slug']);

        $violations = $this->violations($this->api('PUT', '/api/admin/catalog/categories/'.$engineOil['id'], ['name' => 'Engine oil', 'slug' => 'engine-oil', 'parentId' => $engineOil['children'][0]['id']], 422));
        self::assertSame('A category cannot be placed inside itself.', $violations['parentId']);

        $new = $this->api('POST', '/api/admin/catalog/categories', ['name' => 'Brake fluids', 'slug' => 'brake-fluids'], 201);
        $this->api('DELETE', '/api/admin/catalog/categories/'.$new['id'], null, 204);
    }

    public function testAttributeRules(): void
    {
        $this->loginInStore('auto');
        $attributes = $this->api('GET', '/api/admin/catalog/attributes');
        $sae = array_values(array_filter($attributes, static fn ($a) => 'sae_viscosity' === $a['code']))[0];

        self::assertGreaterThan(0, $sae['productCount']);
        $this->api('DELETE', '/api/admin/catalog/attributes/'.$sae['id'], null, 422);

        $violations = $this->violations($this->api('POST', '/api/admin/catalog/attributes', ['code' => 'colour', 'name' => 'Colour', 'type' => 'select', 'options' => []], 422));
        self::assertSame('Add at least one option for a select attribute.', $violations['options']);

        $created = $this->api('POST', '/api/admin/catalog/attributes', ['code' => 'colour', 'name' => 'Colour', 'type' => 'select', 'options' => ['Amber', 'Blue']], 201);
        $this->api('DELETE', '/api/admin/catalog/attributes/'.$created['id'], null, 204);
    }

    public function testAnotherStoresProductCannotBeEdited(): void
    {
        $this->loginInStore('industrie');
        $this->client->request('GET', self::ADMIN.'/api/admin/catalog/products');
        $industrieIds = array_column(json_decode((string) $this->client->getResponse()->getContent(), true)['items'], 'publicId');

        $this->loginInStore('auto');
        $this->api('GET', '/api/admin/catalog/products/'.$industrieIds[0], null, 404);
    }

    private function loginInStore(string $store): void
    {
        $staff = StaffUserFactory::createOne();
        StoreMembershipFactory::createOne(['staffUser' => $staff, 'store' => $this->stores[$store], 'role' => StoreRole::Manager]);
        $this->loginAs($staff);
        $this->api('PUT', '/api/admin/stores/current', ['store' => $this->stores[$store]->getPublicId()->toRfc4122()], 204);
    }

    private function loginAs(StaffUser $staff): void
    {
        $this->client->loginUser($staff, 'admin');
        $this->client->request('GET', self::ADMIN.'/api/csrf-token');
        $this->token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'];
    }

    /**
     * @return array<string, mixed>
     */
    private function productForm(string $search): array
    {
        $publicId = $this->api('GET', '/api/admin/catalog/products?q='.$search)['items'][0]['publicId'];

        return $this->api('GET', '/api/admin/catalog/products/'.$publicId);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed>
     */
    private function api(string $method, string $path, ?array $body = null, int $expected = 200): array
    {
        $this->client->jsonRequest($method, self::ADMIN.$path, $body ?? [], ['HTTP_X_CSRF_TOKEN' => $this->token]);
        self::assertResponseStatusCodeSame($expected, (string) $this->client->getResponse()->getContent());
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed> $problem
     *
     * @return array<string, string>
     */
    private function violations(array $problem): array
    {
        return array_column($problem['violations'] ?? [], 'message', 'propertyPath');
    }
}
