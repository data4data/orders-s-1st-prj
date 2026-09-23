<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin shipping method form. Amounts in euros net of VAT; weight brackets in kg.
 */
final class ShippingMethodInput
{
    #[Assert\NotBlank(message: 'Enter a code.')]
    #[Assert\Regex(pattern: '/^[a-z0-9_-]{2,40}$/', message: 'Use lower-case letters, digits, - or _.')]
    public string $code = '';

    #[Assert\NotBlank(message: 'Enter a name.')]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\Length(max: 200)]
    public ?string $description = null;

    #[Assert\Choice(choices: ['flat', 'weight_based', 'free_over_threshold'], message: 'Choose how the price is calculated.')]
    public string $calculator = 'flat';

    #[Assert\When(expression: 'this.calculator != "weight_based"', constraints: [new Assert\NotBlank(message: 'Enter the price.'), new Assert\Regex(pattern: '/^\d{1,5}(\.\d{1,2})?$/', message: 'Enter an amount like 5.78 (net).')])]
    public ?string $amount = null;

    #[Assert\When(expression: 'this.calculator == "free_over_threshold"', constraints: [new Assert\NotBlank(message: 'Enter the order value for free shipping.'), new Assert\Regex(pattern: '/^\d{1,6}(\.\d{1,2})?$/', message: 'Enter an amount like 100.00 (incl. VAT).')])]
    public ?string $threshold = null;

    /**
     * @var list<array{upToKg: ?string, amount: string}>
     */
    #[Assert\When(expression: 'this.calculator == "weight_based"', constraints: [new Assert\Count(min: 1, minMessage: 'Add at least one weight bracket.')])]
    public array $brackets = [];

    /** @var list<string> empty = every country */
    public array $allowedCountries = [];

    public int $position = 0;

    public bool $isActive = true;
}
