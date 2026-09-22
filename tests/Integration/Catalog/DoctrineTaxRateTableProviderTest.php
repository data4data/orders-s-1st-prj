<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Domain\Tax\NoTaxRateException;
use App\Infrastructure\Fixtures\CatalogBuilder;
use App\Infrastructure\Fixtures\Factory\CountryFactory;
use App\Infrastructure\Tax\DoctrineTaxRateTableProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The seeded Dutch VAT periods come back from the database as the pure-PHP rate table.
 */
final class DoctrineTaxRateTableProviderTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testDutchRatesPerPeriod(): void
    {
        self::getContainer()->get(CatalogBuilder::class)->dutchVat(CountryFactory::netherlands());
        $table = self::getContainer()->get(DoctrineTaxRateTableProvider::class)->table();

        self::assertSame('21.00', $table->rateFor('NL', 'standard', new \DateTimeImmutable('2026-09-22'))->toString());
        self::assertSame('6.00', $table->rateFor('NL', 'reduced', new \DateTimeImmutable('2018-12-31'))->toString());
        self::assertSame('9.00', $table->rateFor('NL', 'reduced', new \DateTimeImmutable('2019-01-01'))->toString());
        self::assertSame('0.00', $table->rateFor('NL', 'zero', new \DateTimeImmutable('2026-09-22'))->toString());

        $this->expectException(NoTaxRateException::class);
        $table->rateFor('NL', 'standard', new \DateTimeImmutable('2012-09-30'));
    }
}
