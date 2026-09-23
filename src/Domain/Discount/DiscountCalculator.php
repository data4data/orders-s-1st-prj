<?php

declare(strict_types=1);

namespace App\Domain\Discount;

/**
 * Applies every rule that supports the cart, one after another, each on what is left after
 * the previous ones, so the total discount can never exceed the items total.
 */
final readonly class DiscountCalculator
{
    /**
     * @param iterable<DiscountRuleInterface> $rules
     */
    public function __construct(private iterable $rules)
    {
    }

    /** @throws CouponNotApplicableException */
    public function calculate(DiscountContext $context): DiscountResult
    {
        $result = DiscountResult::none($context->currency);
        foreach ($this->rules as $rule) {
            $remaining = $context->after($result);
            if ($rule->supports($remaining)) {
                $result = $result->plus($rule->apply($remaining));
            }
        }

        return $result;
    }
}
