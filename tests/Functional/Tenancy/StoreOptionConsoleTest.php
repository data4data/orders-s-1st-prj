<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tenancy;

use App\Infrastructure\Fixtures\Factory\StoreFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class StoreOptionConsoleTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testTheStoreOptionScopesACommand(): void
    {
        $tester = $this->tester();
        StoreFactory::createOne(['code' => 'myoils-auto', 'name' => "MyOil's Auto"]);

        $tester->run(['command' => 'app:tenant:status', '--store' => 'myoils-auto']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString("MyOil's Auto", $tester->getDisplay());
    }

    public function testWithoutTheOptionNoStoreIsActive(): void
    {
        $tester = $this->tester();

        $tester->run(['command' => 'app:tenant:status']);

        self::assertStringContainsString('No store is active', $tester->getDisplay());
    }

    public function testAnUnknownStoreIsRejected(): void
    {
        $tester = $this->tester();

        $tester->run(['command' => 'app:tenant:status', '--store' => 'nope']);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Unknown store "nope"', $tester->getDisplay());
    }

    private function tester(): ApplicationTester
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);

        return new ApplicationTester($application);
    }
}
