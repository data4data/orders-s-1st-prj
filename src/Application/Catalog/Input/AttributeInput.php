<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin form data for a specification attribute and its options.
 */
final class AttributeInput
{
    #[Assert\NotBlank(message: 'Enter a code.')]
    #[Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/', message: 'Use lower-case letters, digits and underscores, e.g. sae_viscosity.')]
    #[Assert\Length(max: 64)]
    public string $code = '';

    #[Assert\NotBlank(message: 'Enter a name.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\Choice(choices: ['select', 'multiselect', 'number', 'text'], message: 'Choose a type.')]
    public string $type = 'select';

    #[Assert\Length(max: 20)]
    public ?string $unit = null;

    public bool $isFilterable = true;

    #[Assert\Range(min: 0, max: 9999)]
    public int $position = 0;

    /** @var list<string> */
    #[Assert\All([new Assert\NotBlank(message: 'An option cannot be empty.'), new Assert\Length(max: 120)])]
    #[Assert\Unique(message: 'Each option may appear only once.')]
    public array $options = [];
}
