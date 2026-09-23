<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tax;

use App\Domain\Money\Money;
use App\Domain\Tax\NoTaxRateException;
use App\Domain\Tax\StoreCountryTaxRateResolver;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxRate;
use App\Domain\Tax\TaxRatePeriod;
use App\Domain\Tax\TaxRateTable;
use App\Domain\Tax\TaxRateTableProviderInterface;
use PHPUnit\Framework\TestCase;

final class TaxTest extends TestCase
{
    public function testTaxRateCalculatesVatAndGross(): void
    {
        $rate = TaxRate::of('21.00');

        self::assertSame(867, $rate->taxOn(Money::of(4128, 'EUR'))->amount);
        self::assertSame(4995, $rate->grossFor(Money::of(4128, 'EUR'))->amount, 'catalog net 41.28 -> 49.95 incl. VAT');
        self::assertSame('21.00', $rate->toString());
        self::assertTrue($rate->equals(TaxRate::of(21)));
    }

    public function testTheRateValidOnTheDateApplies(): void
    {
        $table = $this->netherlands();

        self::assertSame('6.00', $table->rateFor('NL', 'reduced', new \DateTimeImmutable('2018-12-31'))->toString());
        self::assertSame('9.00', $table->rateFor('nl', 'reduced', new \DateTimeImmutable('2019-01-01'))->toString());
        self::assertSame('21.00', $table->rateFor('NL', 'standard', new \DateTimeImmutable('2026-09-21'))->toString());
    }

    public function testAMissingRateIsReported(): void
    {
        $this->expectException(NoTaxRateException::class);
        $this->expectExceptionMessage('No VAT rate is configured for DE / standard on 2026-09-21.');
        $this->netherlands()->rateFor('de', 'standard', new \DateTimeImmutable('2026-09-21'));
    }

    public function testRatesBeforeTheFirstPeriodAreMissing(): void
    {
        $this->expectException(NoTaxRateException::class);
        $this->netherlands()->rateFor('NL', 'reduced', new \DateTimeImmutable('2000-01-01'));
    }

    public function testOverlappingPeriodsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TaxRateTable([
            new TaxRatePeriod('NL', 'standard', TaxRate::of('21'), new \DateTimeImmutable('2012-10-01')),
            new TaxRatePeriod('NL', 'standard', TaxRate::of('22'), new \DateTimeImmutable('2026-01-01')),
        ]);
    }

    public function testPeriodsThatTouchDoNotOverlap(): void
    {
        $a = new TaxRatePeriod('NL', 'x', TaxRate::of('1'), new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-12-31'));
        $b = new TaxRatePeriod('NL', 'x', TaxRate::of('2'), new \DateTimeImmutable('2021-01-01'));

        self::assertFalse($a->overlaps($b));
        self::assertFalse($b->overlaps($a));
        self::assertFalse($a->overlaps(new TaxRatePeriod('DE', 'x', TaxRate::of('1'), new \DateTimeImmutable('2020-01-01'))));
    }

    public function testAPeriodCannotEndBeforeItStarts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TaxRatePeriod('NL', 'standard', TaxRate::of('21'), new \DateTimeImmutable('2020-01-02'), new \DateTimeImmutable('2020-01-01'));
    }

    public function testTheStoreCountryDecides(): void
    {
        $table = $this->netherlands();
        $resolver = new StoreCountryTaxRateResolver(new class($table) implements TaxRateTableProviderInterface {
            public function __construct(private readonly TaxRateTable $table)
            {
            }

            public function table(): TaxRateTable
            {
                return $this->table;
            }
        });

        $context = new TaxContext('NL', 'standard', new \DateTimeImmutable('2026-09-21'), destinationCountryCode: 'DE');

        self::assertSame('21.00', $resolver->resolve($context)->toString());
    }

    private function netherlands(): TaxRateTable
    {
        return new TaxRateTable([
            new TaxRatePeriod('NL', 'standard', TaxRate::of('21.00'), new \DateTimeImmutable('2012-10-01')),
            new TaxRatePeriod('NL', 'reduced', TaxRate::of('6.00'), new \DateTimeImmutable('2001-01-01'), new \DateTimeImmutable('2018-12-31')),
            new TaxRatePeriod('NL', 'reduced', TaxRate::of('9.00'), new \DateTimeImmutable('2019-01-01')),
        ]);
    }
}
