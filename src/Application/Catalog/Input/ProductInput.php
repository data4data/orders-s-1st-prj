<?php

declare(strict_types=1);

namespace App\Application\Catalog\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The whole admin product form, saved at once: general, pack sizes & stock, specs, images, documents.
 * `version` is the version the form was loaded with (optimistic lock, 409 when stale).
 */
final class ProductInput
{
    public ?int $version = null;

    #[Assert\NotBlank(message: 'Enter a product name.')]
    #[Assert\Length(max: 160)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Enter a URL name.')]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Use lower-case letters, digits and single dashes, e.g. synth-pro-5w-30.')]
    #[Assert\Length(max: 160)]
    public string $slug = '';

    #[Assert\NotBlank(message: 'Enter a brand.')]
    #[Assert\Length(max: 80)]
    public string $brand = "MyOil's";

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\Positive(message: 'Choose a VAT category.')]
    public int $taxCategoryId = 0;

    /** @var list<int> */
    #[Assert\All([new Assert\Positive()])]
    public array $categoryIds = [];

    public bool $isActive = true;

    /** @var list<VariantInput> */
    #[Assert\Count(min: 1, minMessage: 'Add at least one pack size.')]
    #[Assert\Valid]
    public array $variants = [];

    /** @var list<SpecInput> */
    #[Assert\Valid]
    public array $specs = [];

    /** @var list<ImageInput> */
    #[Assert\Valid]
    public array $images = [];

    /** @var list<DocumentInput> */
    #[Assert\Valid]
    public array $documents = [];
}
