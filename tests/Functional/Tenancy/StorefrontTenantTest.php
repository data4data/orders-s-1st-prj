<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tenancy;

use App\Infrastructure\Fixtures\Factory\StoreDomainFactory;
use App\Infrastructure\Fixtures\Factory\StoreFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class StorefrontTenantTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testTheHostSelectsTheStore(): void
    {
        $client = self::createClient();
        StoreDomainFactory::createOne(['host' => 'myoils-auto.shop.test', 'store' => StoreFactory::new(['code' => 'myoils-auto', 'name' => "MyOil's Auto"])]);
        StoreDomainFactory::createOne(['host' => 'myoils-agri.shop.test', 'store' => StoreFactory::new(['code' => 'myoils-agri'])]);

        $client->request('GET', 'https://myoils-auto.shop.test/api/store');
        self::assertResponseIsSuccessful();
        self::assertSame('myoils-auto', $this->json($client->getResponse()->getContent())['code']);

        $client->request('GET', 'https://MYOILS-AGRI.shop.test/api/store');
        self::assertSame('myoils-agri', $this->json($client->getResponse()->getContent())['code']);
    }

    public function testAnUnknownHostIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', 'https://unknown.shop.test/api/store');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnInactiveStoreIsNotFound(): void
    {
        $client = self::createClient();
        StoreDomainFactory::createOne(['host' => 'closed.shop.test', 'store' => StoreFactory::new()->inactive()]);

        $client->request('GET', 'https://closed.shop.test/api/store');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string|false $content): array
    {
        return json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
