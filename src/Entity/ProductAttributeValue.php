<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: one specification value of a product. Select types point at an option
 * (a multiselect has one row per option), number and text types hold the value directly.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product_attribute_value')]
#[ORM\Index(name: 'idx_pav_store_option', columns: ['store_id', 'attribute_option_id'])]
class ProductAttributeValue implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'attributeValues')]
        #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\ManyToOne(targetEntity: Attribute::class)]
        #[ORM\JoinColumn(name: 'attribute_id', nullable: false, onDelete: 'CASCADE')]
        private Attribute $attribute,
        #[ORM\ManyToOne(targetEntity: AttributeOption::class)]
        #[ORM\JoinColumn(name: 'attribute_option_id', nullable: true, onDelete: 'CASCADE')]
        private ?AttributeOption $option = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $valueText = null,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
        private ?string $valueNumber = null,
    ) {
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getOption(): ?AttributeOption
    {
        return $this->option;
    }

    public function getValueText(): ?string
    {
        return $this->valueText;
    }

    public function getValueNumber(): ?string
    {
        return $this->valueNumber;
    }

    /** The value as shown to customers, e.g. "5W-30" or "46 cSt". */
    public function display(): string
    {
        if (null !== $this->option) {
            return $this->option->getValue();
        }
        if (null !== $this->valueNumber) {
            $number = rtrim(rtrim($this->valueNumber, '0'), '.');

            return null !== $this->attribute->getUnit() ? $number.' '.$this->attribute->getUnit() : $number;
        }

        return (string) $this->valueText;
    }
}
