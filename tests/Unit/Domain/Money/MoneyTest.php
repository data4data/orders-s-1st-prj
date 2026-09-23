<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Money;

use App\Domain\Money\CurrencyMismatchException;
use App\Domain\Money\Money;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testCreatesFromCentsAndDecimals(): void
    {
        self::assertSame(4995, Money::of(4995, 'EUR')->amount);
        self::assertSame(4995, Money::fromDecimal('49.95', 'EUR')->amount);
        self::assertSame(4990, Money::fromDecimal('49.9', 'EUR')->amount);
        self::assertSame(1200, Money::fromDecimal('12', 'EUR')->amount);
        self::assertSame('49.95', Money::of(4995, 'EUR')->toDecimalString());
        self::assertSame('0.05', Money::of(5, 'EUR')->toDecimalString());
        self::assertTrue(Money::zero('PLN')->isZero());
    }

    public function testRejectsInvalidCurrencyAndPrecision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::of(100, 'eur');
    }

    public function testRejectsMoreThanTwoDecimals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromDecimal('1.005', 'EUR');
    }

    public function testArithmetic(): void
    {
        $a = Money::of(1000, 'EUR');
        $b = Money::of(250, 'EUR');

        self::assertSame(1250, $a->plus($b)->amount);
        self::assertSame(750, $a->minus($b)->amount);
        self::assertSame(3000, $a->multipliedBy(3)->amount);
        self::assertSame(1050, $a->multipliedByDecimal(BigDecimal::of('1.05'))->amount);
        self::assertSame(4, Money::of(7, 'EUR')->multipliedByDecimal(BigDecimal::of('0.5'))->amount, '3.5 rounds half-up to 4');
        self::assertSame($b, $a->min($b));
        self::assertSame($b, $b->min($a));
    }

    public function testComparisons(): void
    {
        $a = Money::of(1000, 'EUR');
        $b = Money::of(250, 'EUR');

        self::assertTrue($a->isPositive());
        self::assertFalse($a->isNegative());
        self::assertTrue(Money::of(-1, 'EUR')->isNegative());
        self::assertTrue($a->isGreaterThan($b));
        self::assertTrue($a->isGreaterThanOrEqualTo(Money::of(1000, 'EUR')));
        self::assertTrue($b->isLessThan($a));
        self::assertTrue($a->equals(Money::of(1000, 'EUR')));
        self::assertFalse($a->equals(Money::of(1000, 'PLN')));
    }

    public function testRefusesToMixCurrencies(): void
    {
        $this->expectException(CurrencyMismatchException::class);
        $this->expectExceptionMessage('Cannot combine amounts in EUR and PLN.');
        Money::of(100, 'EUR')->plus(Money::of(100, 'PLN'));
    }

    public function testAllocationNeverLosesACent(): void
    {
        $parts = Money::of(1000, 'EUR')->allocate(['a' => 1, 'b' => 1, 'c' => 1]);

        self::assertSame(['a' => 334, 'b' => 333, 'c' => 333], array_map(static fn (Money $m): int => $m->amount, $parts));
    }

    public function testAllocationFollowsWeightsAndLargestRemainder(): void
    {
        // 10.00 over 41.28 and 14.80: 7.36 + 2.64
        $parts = Money::of(1000, 'EUR')->allocate(['oil' => 4128, 'coolant' => 1480]);

        self::assertSame(736, $parts['oil']->amount);
        self::assertSame(264, $parts['coolant']->amount);
        self::assertSame(['x' => 0, 'y' => 0], array_map(static fn (Money $m): int => $m->amount, Money::zero('EUR')->allocate(['x' => 0, 'y' => 0])));
        self::assertSame(['x' => 5, 'y' => 0], array_map(static fn (Money $m): int => $m->amount, Money::of(5, 'EUR')->allocate(['x' => 3, 'y' => 0])));
    }

    /**
     * @return iterable<string, array{Money, array<string, int>}>
     */
    public static function invalidAllocations(): iterable
    {
        yield 'negative amount' => [Money::of(-1, 'EUR'), ['a' => 1]];
        yield 'no weights' => [Money::of(1, 'EUR'), []];
        yield 'negative weight' => [Money::of(1, 'EUR'), ['a' => -1]];
        yield 'all weights zero' => [Money::of(1, 'EUR'), ['a' => 0]];
    }

    /**
     * @param array<string, int> $weights
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAllocations')]
    public function testRejectsInvalidAllocations(Money $money, array $weights): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $money->allocate($weights);
    }
}
