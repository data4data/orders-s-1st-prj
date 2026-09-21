<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke test for Phase 0: the application kernel boots and the service container compiles.
 */
final class KernelBootTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        self::assertTrue(self::getContainer()->has('doctrine'));
    }
}
