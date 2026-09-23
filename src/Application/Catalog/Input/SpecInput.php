<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A product's value(s) for one attribute: options for select types, or a text / number value.
 */
final class SpecInput
{
    #[Assert\Positive]
    public int $attributeId = 0;

    /** @var list<string> */
    public array $options = [];

    #[Assert\Length(max: 255)]
    public ?string $value = null;
}
