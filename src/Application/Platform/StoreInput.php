<?php

declare(strict_types=1);

namespace App\Application\Platform;

use Symfony\Component\Validator\Constraints as Assert;

final class StoreInput
{
    #[Assert\NotBlank(message: 'Enter a code.')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]{3,64}$/', message: 'Use lower-case letters, digits and dashes, e.g. myoils-auto.')]
    public string $code = '';

    #[Assert\NotBlank(message: 'Enter the shop name.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Choose a country.')]
    public string $countryCode = 'NL';

    #[Assert\Regex(pattern: '/^[A-Za-z]{3}$/', message: 'Enter a currency code like EUR.')]
    public string $currencyCode = 'EUR';

    #[Assert\Regex(pattern: '/^[A-Za-z]{2,16}$/', message: 'Use 2 to 16 letters, e.g. AUTO.')]
    public string $orderNumberPrefix = '';

    #[Assert\NotBlank(message: 'Enter the host name of the shop.')]
    #[Assert\Regex(pattern: '/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', message: 'Enter a host name like shop.example.com.')]
    public string $host = '';
}
