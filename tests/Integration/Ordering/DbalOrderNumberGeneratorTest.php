<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ordering;

use App\Infrastructure\Fixtures\Factory\StoreFactory;
use App\Infrastructure\Ordering\DbalOrderNumberGenerator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DbalOrderNumberGeneratorTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testNumbersCountUpPerStoreWithTheStorePrefix(): void
    {
        self::bootKernel();
        // Not used by any service yet (checkout arrives in Phase 5), so Symfony removes it from the container.
        $generator = new DbalOrderNumberGenerator(self::getContainer()->get(Connection::class));
        $auto = StoreFactory::createOne(['orderNumberPrefix' => 'AUTO']);
        $industrie = StoreFactory::createOne(['orderNumberPrefix' => 'IND']);

        self::assertSame('AUTO-000001', $generator->next($auto));
        self::assertSame('AUTO-000002', $generator->next($auto));
        self::assertSame('IND-000001', $generator->next($industrie));
        self::assertSame('AUTO-000003', $generator->next($auto));
    }
}
