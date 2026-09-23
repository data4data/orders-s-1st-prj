<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MaintenanceModeTest extends WebTestCase
{
    private string $flag;

    protected function setUp(): void
    {
        $this->flag = dirname(__DIR__, 3).'/var/maintenance.flag';
    }

    protected function tearDown(): void
    {
        @unlink($this->flag);
        parent::tearDown();
    }

    public function testEveryRequestAnswers503WhileTheFlagExists(): void
    {
        // Branded error pages render only without debug mode (as in production).
        $client = self::createClient(['debug' => false]);
        file_put_contents($this->flag, 'test');

        $client->request('GET', 'https://myoils-auto.shop.test/api/store');
        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '300');

        $client->request('GET', 'https://myoils-auto.shop.test/');
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('h1', "We'll be right back");
    }
}
