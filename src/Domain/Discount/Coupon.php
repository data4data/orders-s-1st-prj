<?php

declare(strict_types=1);

namespace App\Domain\Discount;

use App\Domain\Money\Money;
use App\Domain\Shared\Percentage;

/**
 * The coupon data the discount rules work with (mirrors the coupon table).
 */
final readonly class Coupon
{
    public function __construct(
        public string $code,
        public CouponType $type,
        public ?Percentage $percent = null,
        public ?Money $amount = null,
        public ?Money $minimumOrderNet = null,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validTo = null,
        public ?int $usageLimit = null,
        public int $timesUsed = 0,
        public bool $active = true,
    ) {
        if (CouponType::Percentage === $type && null === $percent) {
            throw new \InvalidArgumentException(sprintf('Percentage coupon "%s" needs a percentage.', $code));
        }
        if (CouponType::Fixed === $type && (null === $amount || !$amount->isPositive())) {
            throw new \InvalidArgumentException(sprintf('Fixed coupon "%s" needs a positive amount.', $code));
        }
    }

    /**
     * @throws CouponNotApplicableException
     */
    public function assertUsable(Money $itemsNet, \DateTimeImmutable $now): void
    {
        $reason = match (true) {
            !$this->active => CouponRejectionReason::Inactive,
            null !== $this->validFrom && $now < $this->validFrom => CouponRejectionReason::NotYetValid,
            null !== $this->validTo && $now > $this->validTo => CouponRejectionReason::Expired,
            null !== $this->usageLimit && $this->timesUsed >= $this->usageLimit => CouponRejectionReason::UsageLimitReached,
            null !== $this->amount && $this->amount->currency !== $itemsNet->currency,
            null !== $this->minimumOrderNet && $this->minimumOrderNet->currency !== $itemsNet->currency => CouponRejectionReason::CurrencyMismatch,
            null !== $this->minimumOrderNet && $itemsNet->isLessThan($this->minimumOrderNet) => CouponRejectionReason::BelowMinimumOrder,
            default => null,
        };

        if (null !== $reason) {
            throw new CouponNotApplicableException($this->code, $reason);
        }
    }
}
