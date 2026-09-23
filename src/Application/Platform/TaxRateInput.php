<?php

declare(strict_types=1);

namespace App\Application\Platform;

use Symfony\Component\Validator\Constraints as Assert;

final class TaxRateInput
{
    #[Assert\NotBlank(message: 'Choose a country.')]
    public string $countryCode = '';

    #[Assert\NotBlank(message: 'Choose a tax category.')]
    public string $taxCategory = '';

    #[Assert\Regex(pattern: '/^\d{1,2}(\.\d{1,2})?$/', message: 'Enter a rate like 21 or 5.5.')]
    public string $rate = '';

    #[Assert\NotBlank(message: 'Enter the first day.')]
    #[Assert\Date(message: 'Enter a date like 2027-01-01.')]
    public string $validFrom = '';

    #[Assert\Date(message: 'Enter a date like 2027-12-31.')]
    public ?string $validTo = null;
}
