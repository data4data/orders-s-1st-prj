<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Money\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PercentageAndQuantityTest extends TestCase
{
    public function testPercentageKeepsTwoDecimals(): void
    {
        self::assertSame('21.00', Percentage::of('21')->toString());
        self::assertSame('5.50', Percentage::of('5.5')->toString());
        self::assertTrue(Percentage::of('21.00')->asFraction()->isEqualTo('0.21'));
        self::assertTrue(Percentage::of(0)->isZero());
        self::assertTrue(Percentage::of('9')->equals(Percentage::of('9.00')));
    }

    public function testPercentageOfMoneyRoundsHalfUp(): void
    {
        self::assertSame(867, Percentage::of('21.00')->applyTo(Money::of(4128, 'EUR'))->amount, '8.6688 -> 8.67');
        self::assertSame(311, Percentage::of('21.00')->applyTo(Money::of(1480, 'EUR'))->amount, '3.108 -> 3.11');
        self::assertSame(1, Percentage::of('50')->applyTo(Money::of(1, 'EUR'))->amount, '0.5 cent rounds up');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPercentages(): iterable
    {
        yield 'not a number' => ['abc'];
        yield 'three decimals' => ['21.005'];
        yield 'negative' => ['-1'];
        yield 'over 100' => ['100.01'];
    }

    #[DataProvider('invalidPercentages')]
    public function testRejectsInvalidPercentages(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Percentage::of($value);
    }

    public function testQuantity(): void
    {
        self::assertSame(5, Quantity::of(2)->plus(Quantity::of(3))->value);

        $this->expectException(\InvalidArgumentException::class);
        Quantity::of(0);
    }
}
