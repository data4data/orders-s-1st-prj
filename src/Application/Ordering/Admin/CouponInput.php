<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin coupon form. Amounts as typed in euros ("5.00"), net of VAT; dates as YYYY-MM-DD.
 */
final class CouponInput
{
    #[Assert\NotBlank(message: 'Enter a code.')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9-]{3,40}$/', message: 'Use 3 to 40 letters, digits or dashes.')]
    public string $code = '';

    #[Assert\Choice(choices: ['percentage', 'fixed'], message: 'Choose percentage or fixed amount.')]
    public string $type = 'percentage';

    #[Assert\When(expression: 'this.type == "percentage"', constraints: [
        new Assert\NotBlank(message: 'Enter the percentage.'),
        new Assert\Regex(pattern: '/^(100(\.0{1,2})?|\d{1,2}(\.\d{1,2})?)$/', message: 'Enter a percentage between 0 and 100, e.g. 10 or 12.5.'),
    ])]
    public ?string $percent = null;

    #[Assert\When(expression: 'this.type == "fixed"', constraints: [
        new Assert\NotBlank(message: 'Enter the amount.'),
        new Assert\Regex(pattern: '/^\d{1,6}(\.\d{1,2})?$/', message: 'Enter an amount like 5.00.'),
    ])]
    public ?string $amount = null;

    #[Assert\Regex(pattern: '/^\d{1,6}(\.\d{1,2})?$/', message: 'Enter an amount like 25.00.')]
    public ?string $minOrderNet = null;

    #[Assert\Date(message: 'Enter a date like 2026-12-31.')]
    public ?string $validFrom = null;

    #[Assert\Date(message: 'Enter a date like 2026-12-31.')]
    public ?string $validTo = null;

    #[Assert\Positive(message: 'Enter a positive number, or leave empty for unlimited.')]
    public ?int $usageLimit = null;

    public bool $isActive = true;

    #[Assert\IsTrue(message: 'The end date must be after the start date.')]
    public function isPeriodValid(): bool
    {
        return null === $this->validFrom || null === $this->validTo || '' === $this->validFrom || '' === $this->validTo || $this->validFrom <= $this->validTo;
    }
}
