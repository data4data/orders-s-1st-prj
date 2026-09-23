<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Pricing;

use App\Domain\Money\Money;
use App\Domain\Pricing\LinePricer;
use App\Domain\Pricing\OrderTotals;
use App\Domain\Pricing\PriceLine;
use App\Domain\Pricing\UnitPrice;
use App\Domain\Shared\Quantity;
use App\Domain\Tax\TaxRate;
use PHPUnit\Framework\TestCase;

/**
 * Uses the numbers from the checkout sketch in docs/diagrams/pages.html.
 */
final class PricingTest extends TestCase
{
    private LinePricer $pricer;

    protected function setUp(): void
    {
        $this->pricer = new LinePricer();
    }

    public function testALineIsPricedNetThenVatRoundedPerLine(): void
    {
        $line = $this->pricer->price(new PriceLine('coolant', Money::of(740, 'EUR'), Quantity::of(2), TaxRate::of('21')));

        self::assertSame(1480, $line->netBeforeDiscount->amount);
        self::assertSame(0, $line->discountNet->amount);
        self::assertSame(1480, $line->net->amount);
        self::assertSame(311, $line->tax->amount, '14.80 × 21% = 3.108 -> 3.11');
        self::assertSame(1791, $line->gross->amount);
    }

    public function testTheDiscountIsTakenOffBeforeVat(): void
    {
        $line = $this->pricer->price(new PriceLine('oil', Money::of(4128, 'EUR'), Quantity::of(1), TaxRate::of('21')), Money::of(736, 'EUR'));

        self::assertSame(3392, $line->net->amount);
        self::assertSame(712, $line->tax->amount, '33.92 × 21% = 7.1232 -> 7.12');
        self::assertSame(4104, $line->gross->amount);
    }

    public function testADiscountLargerThanTheLineIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->pricer->price(new PriceLine('oil', Money::of(100, 'EUR'), Quantity::of(1), TaxRate::of('21')), Money::of(101, 'EUR'));
    }

    public function testANegativeUnitPriceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PriceLine('oil', Money::of(-1, 'EUR'), Quantity::of(1), TaxRate::of('21'));
    }

    public function testOrderTotalsMatchTheCheckoutExample(): void
    {
        $oil = $this->pricer->price(new PriceLine('oil', Money::of(4128, 'EUR'), Quantity::of(1), TaxRate::of('21')));
        $coolant = $this->pricer->price(new PriceLine('coolant', Money::of(740, 'EUR'), Quantity::of(2), TaxRate::of('21')));

        $totals = OrderTotals::calculate('EUR', [$oil, $coolant]);

        self::assertSame('56.08', $totals->itemsNet->toDecimalString());
        self::assertSame('0.00', $totals->discountNet->toDecimalString());
        self::assertSame('0.00', $totals->shippingNet->toDecimalString());
        self::assertSame('56.08', $totals->totalNet->toDecimalString());
        self::assertSame('11.78', $totals->totalTax->toDecimalString());
        self::assertSame('67.86', $totals->totalGross->toDecimalString());
    }

    public function testShippingIsAddedWithItsOwnVat(): void
    {
        $oil = $this->pricer->price(new PriceLine('oil', Money::of(4128, 'EUR'), Quantity::of(1), TaxRate::of('21')), Money::of(128, 'EUR'));
        $shipping = $this->pricer->price(new PriceLine('shipping', Money::of(574, 'EUR'), Quantity::of(1), TaxRate::of('21')));

        $totals = OrderTotals::calculate('EUR', [$oil], $shipping);

        self::assertSame(4128, $totals->itemsNet->amount);
        self::assertSame(128, $totals->discountNet->amount);
        self::assertSame(574, $totals->shippingNet->amount);
        self::assertSame(4574, $totals->totalNet->amount);
        self::assertSame(840 + 121, $totals->totalTax->amount);
        self::assertSame(4574 + 961, $totals->totalGross->amount);
    }

    public function testPricePerLitre(): void
    {
        self::assertSame('9.99', UnitPrice::perLitre(Money::of(4995, 'EUR'), 5000)->toDecimalString());
        self::assertSame('7.16', UnitPrice::perLitre(Money::of(148900, 'EUR'), 208000)->toDecimalString());
        self::assertSame('1.79', UnitPrice::perLitre(Money::of(895, 'EUR'), 5000)->toDecimalString());

        $this->expectException(\InvalidArgumentException::class);
        UnitPrice::perLitre(Money::of(100, 'EUR'), 0);
    }
}
