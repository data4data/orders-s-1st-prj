<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The server side of the error-handling standard (docs/diagrams/pages.html → Error handling).
 */
final class ErrorHandlingTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->catchExceptions(true);
        StoreDomainFactory::createOne(['host' => 'myoils-auto.shop.test', 'store' => StoreFactory::new(['code' => 'myoils-auto', 'name' => "MyOil's Auto", 'primaryColor' => '#0F2742'])]);
    }

    public function testEveryResponseCarriesAReferenceCode(): void
    {
        $this->client->request('GET', 'https://myoils-auto.shop.test/api/store');

        self::assertMatchesRegularExpression('/^[0-9A-F]{4}-[0-9A-F]{4}$/', (string) $this->client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testApiErrorsAreProblemJsonWithTheReferenceCode(): void
    {
        $this->client->request('GET', 'https://myoils-auto.shop.test/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $body = $this->json();
        self::assertSame(404, $body['status']);
        self::assertSame('Not Found', $body['title']);
        self::assertSame($this->client->getResponse()->headers->get('X-Request-Id'), $body['requestId']);
    }

    public function testAWrongMethodIsProblemJson(): void
    {
        // The router answers before the CSRF check (AdminStoreSwitchTest covers 419 on a real PUT).
        $this->client->jsonRequest('POST', 'https://myoils-auto.shop.test/api/store');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(405, $this->json()['status']);
    }

    public function testAFreshCsrfTokenCanBeFetched(): void
    {
        $this->client->request('GET', 'https://myoils-auto.shop.test/api/csrf-token');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->json()['token']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testPageErrorsUseTheBrandedTemplate(): void
    {
        $this->productionLikeClient();
        $this->client->request('GET', 'https://myoils-auto.shop.test/no-such-page');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', "We can't find this page");
        self::assertSelectorExists('#brand-variables');
        self::assertStringContainsString('--brand-primary:#0F2742', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('.shop-brand', "MyOil's Auto");
    }

    public function testAnUnknownHostShowsTheNeutralStoreNotFoundPage(): void
    {
        $this->productionLikeClient();
        $this->client->request('GET', 'https://unknown.shop.test/');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Store not found');
        self::assertSelectorNotExists('.shop-brand');
        self::assertStringContainsString('--brand-primary:#2563EB', (string) $this->client->getResponse()->getContent());
    }

    /** Branded error pages render only without debug mode (as in production). */
    private function productionLikeClient(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient(['debug' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
