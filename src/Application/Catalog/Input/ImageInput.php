<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * An image link (decision #39: https URLs only, no upload yet).
 */
final class ImageInput
{
    #[Assert\NotBlank(message: 'Enter the image address.')]
    #[Assert\Url(message: 'Enter a full address starting with https://.', requireTld: true, protocols: ['https'])]
    #[Assert\Length(max: 500)]
    public string $url = '';

    #[Assert\NotBlank(message: 'Describe the image for screen readers.')]
    #[Assert\Length(max: 200)]
    public string $alt = '';

    /** Pack-specific photo: the SKU of that pack, or null for all packs. */
    public ?string $variantSku = null;
}
