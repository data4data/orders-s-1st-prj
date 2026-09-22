<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final class StoreProfileInput
{
    #[Assert\NotBlank(message: 'Enter the shop name.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\Email(message: 'Enter a valid email address.')]
    public ?string $contactEmail = null;

    #[Assert\Url(message: 'Enter a full address starting with https://.', protocols: ['https'], requireTld: true)]
    public ?string $logoUrl = null;

    #[Assert\Url(message: 'Enter a full address starting with https://.', protocols: ['https'], requireTld: true)]
    public ?string $faviconUrl = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Use a colour like #0F2742.')]
    public string $primaryColor = '#0F2742';

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Use a colour like #F2A900.')]
    public string $accentColor = '#F2A900';

    #[Assert\Regex(pattern: '/^[A-Za-z]{2,16}$/', message: 'Use 2 to 16 letters, e.g. AUTO.')]
    public string $orderNumberPrefix = '';

    #[Assert\Range(min: 0, max: 10000, notInRangeMessage: 'Enter a number between 0 and 10000.')]
    public int $lowStockThreshold = 10;
}
