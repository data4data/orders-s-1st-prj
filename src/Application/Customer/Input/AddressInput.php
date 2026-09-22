<?php

declare(strict_types=1);

namespace App\Application\Customer\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * An address as typed in registration, the address book or checkout.
 */
final class AddressInput
{
    #[Assert\Length(max: 60)]
    public ?string $label = null;

    #[Assert\NotBlank(message: 'Enter the first name.')]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Enter the last name.')]
    #[Assert\Length(max: 100)]
    public string $lastName = '';

    #[Assert\Length(max: 150)]
    public ?string $company = null;

    #[Assert\Length(max: 32)]
    #[Assert\Regex(pattern: '/^[A-Z]{2}[A-Z0-9]{2,12}$/', message: 'Enter a VAT number like NL123456789B01.')]
    public ?string $vatId = null;

    #[Assert\NotBlank(message: 'Enter the street.')]
    #[Assert\Length(max: 150)]
    public string $street = '';

    #[Assert\NotBlank(message: 'Enter the house number.')]
    #[Assert\Length(max: 20)]
    public string $houseNumber = '';

    #[Assert\NotBlank(message: 'Enter the postcode.')]
    #[Assert\Length(max: 16)]
    #[Assert\When(
        expression: 'this.countryCode == "NL"',
        constraints: [new Assert\Regex(pattern: '/^\d{4}\s?[A-Za-z]{2}$/', message: 'Enter a Dutch postcode like 1012 AB.')],
    )]
    public string $postcode = '';

    #[Assert\NotBlank(message: 'Enter the city.')]
    #[Assert\Length(max: 100)]
    public string $city = '';

    #[Assert\NotBlank(message: 'Choose a country.')]
    #[Assert\Length(exactly: 2)]
    public string $countryCode = 'NL';

    #[Assert\Length(max: 32)]
    #[Assert\Regex(pattern: '/^\+?[0-9 ()-]{6,}$/', message: 'Enter a phone number like +31 20 123 4567.')]
    public ?string $phone = null;

    public bool $usableForBilling = true;

    public bool $usableForShipping = true;

    #[Assert\IsTrue(message: 'An address must be usable for billing, delivery or both.')]
    public function isUsableForSomething(): bool
    {
        return $this->usableForBilling || $this->usableForShipping;
    }
}
