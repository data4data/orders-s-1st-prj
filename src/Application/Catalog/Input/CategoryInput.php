<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin form data for a category.
 */
final class CategoryInput
{
    #[Assert\NotBlank(message: 'Enter a name.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Enter a URL name.')]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Use lower-case letters, digits and single dashes, e.g. engine-oil.')]
    #[Assert\Length(max: 120)]
    public string $slug = '';

    #[Assert\Positive]
    public ?int $parentId = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Range(min: 0, max: 9999)]
    public int $position = 0;

    public bool $isActive = true;
}
