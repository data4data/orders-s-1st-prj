<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One pack size in the admin product form. Price as entered, net, e.g. "41.28".
 */
final class VariantInput
{
    public ?string $publicId = null;

    #[Assert\NotBlank(message: 'Enter an SKU.')]
    #[Assert\Regex(pattern: '/^[A-Z0-9][A-Z0-9-]*$/', message: 'Use capitals, digits and dashes, e.g. SP530-5.')]
    #[Assert\Length(max: 64)]
    public string $sku = '';

    #[Assert\NotBlank(message: 'Enter a pack name, e.g. 5 L.')]
    #[Assert\Length(max: 60)]
    public string $name = '';

    #[Assert\Positive(message: 'Enter the volume in ml.')]
    public int $volumeMl = 0;

    #[Assert\PositiveOrZero(message: 'Enter the weight in grams.')]
    public int $weightG = 0;

    #[Assert\NotBlank(message: 'Enter the net price.')]
    #[Assert\Regex(pattern: '/^\d{1,7}(\.\d{1,2})?$/', message: 'Enter an amount like 41.28 (net, without VAT).')]
    public string $priceNet = '';

    #[Assert\PositiveOrZero(message: 'Stock cannot be negative.')]
    public int $onHand = 0;

    public bool $isActive = true;
}
