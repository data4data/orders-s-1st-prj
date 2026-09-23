<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Discount;

use App\Domain\Discount\Coupon;
use App\Domain\Discount\CouponFixedAmountRule;
use App\Domain\Discount\CouponNotApplicableException;
use App\Domain\Discount\CouponPercentageRule;
use App\Domain\Discount\CouponRejectionReason;
use App\Domain\Discount\CouponType;
use App\Domain\Discount\DiscountableLine;
use App\Domain\Discount\DiscountCalculator;
use App\Domain\Discount\DiscountContext;
use App\Domain\Discount\DiscountResult;
use App\Domain\Discount\DiscountRuleInterface;
use App\Domain\Money\Money;
use App\Domain\Shared\Percentage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscountTest extends TestCase
{
    private DiscountCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DiscountCalculator([new CouponPercentageRule(), new CouponFixedAmountRule()]);
    }

    public function testNoCouponMeansNoDiscount(): void
    {
        $result = $this->calculator->calculate($this->cart(null));

        self::assertTrue($result->total->isZero());
        self::assertSame([], $result->perLine);
        self::assertTrue($result->forLine('oil')->isZero());
    }

    public function testPercentageCouponIsSplitOverLines(): void
    {
        $result = $this->calculator->calculate($this->cart(new Coupon('SUMMER10', CouponType::Percentage, Percentage::of('10'))));

        self::assertSame(561, $result->total->amount, '10% of 56.08 = 5.608 -> 5.61');
        self::assertSame(413, $result->forLine('oil')->amount);
        self::assertSame(148, $result->forLine('coolant')->amount);
        self::assertSame(['SUMMER10'], $result->codes);
    }

    public function testFixedCouponIsCappedAtTheItemsTotal(): void
    {
        $result = $this->calculator->calculate($this->cart(new Coupon('BIG', CouponType::Fixed, amount: Money::of(10000, 'EUR'))));

        self::assertSame(5608, $result->total->amount);
        self::assertSame(4128, $result->forLine('oil')->amount);
        self::assertSame(1480, $result->forLine('coolant')->amount);
    }

    public function testFixedCouponOnAnEmptyCart(): void
    {
        $context = new DiscountContext('EUR', [], new Coupon('TEN', CouponType::Fixed, amount: Money::of(1000, 'EUR')), new \DateTimeImmutable());

        $result = $this->calculator->calculate($context);

        self::assertTrue($result->total->isZero());
    }

    public function testRulesApplyToWhatIsLeftAfterEarlierRules(): void
    {
        $everything = new class implements DiscountRuleInterface {
            public function supports(DiscountContext $context): bool
            {
                return true;
            }

            public function apply(DiscountContext $context): DiscountResult
            {
                $perLine = [];
                foreach ($context->lines as $line) {
                    $perLine[$line->lineId] = $line->net;
                }

                return new DiscountResult($context->itemsNet(), $perLine, ['ALL']);
            }
        };
        $calculator = new DiscountCalculator([$everything, $everything]);

        $result = $calculator->calculate($this->cart(null));

        self::assertSame(5608, $result->total->amount, 'the second rule finds nothing left to discount');
        self::assertSame(['ALL', 'ALL'], $result->codes);
    }

    /**
     * @return iterable<string, array{Coupon, CouponRejectionReason}>
     */
    public static function unusableCoupons(): iterable
    {
        $percent = Percentage::of('10');
        yield 'inactive' => [new Coupon('X', CouponType::Percentage, $percent, active: false), CouponRejectionReason::Inactive];
        yield 'not yet valid' => [new Coupon('X', CouponType::Percentage, $percent, validFrom: new \DateTimeImmutable('2026-10-01')), CouponRejectionReason::NotYetValid];
        yield 'expired' => [new Coupon('X', CouponType::Percentage, $percent, validTo: new \DateTimeImmutable('2026-08-31')), CouponRejectionReason::Expired];
        yield 'used up' => [new Coupon('X', CouponType::Percentage, $percent, usageLimit: 5, timesUsed: 5), CouponRejectionReason::UsageLimitReached];
        yield 'below minimum' => [new Coupon('X', CouponType::Percentage, $percent, minimumOrderNet: Money::of(10000, 'EUR')), CouponRejectionReason::BelowMinimumOrder];
        yield 'fixed amount in other currency' => [new Coupon('X', CouponType::Fixed, amount: Money::of(100, 'PLN')), CouponRejectionReason::CurrencyMismatch];
        yield 'minimum in other currency' => [new Coupon('X', CouponType::Percentage, $percent, minimumOrderNet: Money::of(100, 'PLN')), CouponRejectionReason::CurrencyMismatch];
    }

    #[DataProvider('unusableCoupons')]
    public function testUnusableCouponsAreRejectedWithAReason(Coupon $coupon, CouponRejectionReason $reason): void
    {
        try {
            $this->calculator->calculate($this->cart($coupon));
            self::fail('Expected the coupon to be rejected.');
        } catch (CouponNotApplicableException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertSame('X', $exception->couponCode);
            self::assertStringContainsString('cannot be used', $exception->getMessage());
        }
    }

    public function testAValidCouponWithAllLimitsPasses(): void
    {
        $coupon = new Coupon('OK', CouponType::Percentage, Percentage::of('10'), minimumOrderNet: Money::of(1000, 'EUR'),
            validFrom: new \DateTimeImmutable('2026-01-01'), validTo: new \DateTimeImmutable('2026-12-31'), usageLimit: 10, timesUsed: 3);

        self::assertSame(561, $this->calculator->calculate($this->cart($coupon))->total->amount);
    }

    public function testCouponsNeedTheirValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coupon('X', CouponType::Percentage);
    }

    public function testFixedCouponsNeedAPositiveAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coupon('X', CouponType::Fixed, amount: Money::zero('EUR'));
    }

    private function cart(?Coupon $coupon): DiscountContext
    {
        return new DiscountContext('EUR', [
            new DiscountableLine('oil', Money::of(4128, 'EUR')),
            new DiscountableLine('coolant', Money::of(1480, 'EUR')),
        ], $coupon, new \DateTimeImmutable('2026-09-21'));
    }
}
