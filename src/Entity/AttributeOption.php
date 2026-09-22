<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: one allowed value of a select attribute, e.g. "5W-30".
 */
#[ORM\Entity]
#[ORM\Table(name: 'attribute_option')]
class AttributeOption implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Attribute::class, inversedBy: 'options')]
        #[ORM\JoinColumn(name: 'attribute_id', nullable: false, onDelete: 'CASCADE')]
        private Attribute $attribute,
        #[ORM\Column(length: 120)]
        private string $value,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }
}
