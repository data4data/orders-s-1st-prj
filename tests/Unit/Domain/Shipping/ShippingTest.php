<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shipping;

use App\Domain\Money\Money;
use App\Domain\Shipping\FlatRateCalculator;
use App\Domain\Shipping\FreeOverThresholdCalculator;
use App\Domain\Shipping\InvalidShippingConfigException;
use App\Domain\Shipping\Shipment;
use App\Domain\Shipping\ShippingMethodSpec;
use App\Domain\Shipping\ShippingNotAvailableException;
use App\Domain\Shipping\ShippingQuoter;
use App\Domain\Shipping\WeightBasedCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShippingTest extends TestCase
{
    private ShippingQuoter $quoter;

    protected function setUp(): void
    {
        $this->quoter = new ShippingQuoter([new FlatRateCalculator(), new WeightBasedCalculator(), new FreeOverThresholdCalculator()]);
    }

    public function testFlatRate(): void
    {
        self::assertSame(695, $this->quote(new ShippingMethodSpec('standard', 'flat', ['amount' => 695]), 6786)->amount);
    }

    public function testFreeOverThreshold(): void
    {
        $method = new ShippingMethodSpec('free100', 'free_over_threshold', ['amount' => 695, 'threshold' => 10000]);

        self::assertSame(695, $this->quote($method, 9999)->amount);
        self::assertSame(0, $this->quote($method, 10000)->amount);
    }

    public function testWeightBrackets(): void
    {
        $method = new ShippingMethodSpec('parcel', 'weight_based', ['brackets' => [
            ['up_to_grams' => 5000, 'amount' => 695],
            ['up_to_grams' => 30000, 'amount' => 1295],
            ['up_to_grams' => null, 'amount' => 4995],
        ]]);

        self::assertSame(695, $this->quote($method, 1000, 5000)->amount);
        self::assertSame(1295, $this->quote($method, 1000, 5001)->amount);
        self::assertSame(4995, $this->quote($method, 1000, 220000)->amount, 'a 208 L drum goes by pallet');
    }

    public function testTooHeavyForAllBrackets(): void
    {
        $this->expectException(ShippingNotAvailableException::class);
        $this->quote(new ShippingMethodSpec('small', 'weight_based', ['brackets' => [['up_to_grams' => 5000, 'amount' => 695]]]), 1000, 9000);
    }

    public function testAllowedCountries(): void
    {
        $nlOnly = new ShippingMethodSpec('nl', 'flat', ['amount' => 695], ['nl']);

        self::assertTrue($nlOnly->shipsTo('NL'));
        self::assertFalse($nlOnly->shipsTo('BE'));
        self::assertTrue((new ShippingMethodSpec('all', 'flat', ['amount' => 1], []))->shipsTo('PL'));

        $this->expectException(ShippingNotAvailableException::class);
        $this->expectExceptionMessage('Shipping method "nl" does not ship to BE.');
        $this->quoter->quote($nlOnly, new Shipment(Money::of(100, 'EUR'), 100, 'be'));
    }

    /**
     * @return iterable<string, array{ShippingMethodSpec}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown calculator' => [new ShippingMethodSpec('x', 'drone', [])];
        yield 'flat without amount' => [new ShippingMethodSpec('x', 'flat', [])];
        yield 'negative amount' => [new ShippingMethodSpec('x', 'flat', ['amount' => -1])];
        yield 'amount as string' => [new ShippingMethodSpec('x', 'flat', ['amount' => '6.95'])];
        yield 'no brackets' => [new ShippingMethodSpec('x', 'weight_based', ['brackets' => []])];
        yield 'bracket not a list' => [new ShippingMethodSpec('x', 'weight_based', ['brackets' => ['oops']])];
    }

    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigsAreReported(ShippingMethodSpec $method): void
    {
        $this->expectException(InvalidShippingConfigException::class);
        $this->quote($method, 1000);
    }

    public function testNegativeWeightIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Shipment(Money::of(1, 'EUR'), -1, 'NL');
    }

    private function quote(ShippingMethodSpec $method, int $orderValueCents, int $weightGrams = 1000): Money
    {
        return $this->quoter->quote($method, new Shipment(Money::of($orderValueCents, 'EUR'), $weightGrams, 'NL'));
    }
}
