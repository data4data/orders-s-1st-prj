<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Catalog\AttributeType;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a product specification such as "SAE viscosity" or "ISO VG" (decision #32).
 */
#[ORM\Entity]
#[ORM\Table(name: 'attribute')]
#[ORM\UniqueConstraint(name: 'uniq_attribute_store_code', columns: ['store_id', 'code'])]
class Attribute implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column]
    private bool $isFilterable = true;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, AttributeOption> */
    #[ORM\OneToMany(targetEntity: AttributeOption::class, mappedBy: 'attribute', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'value' => 'ASC'])]
    private Collection $options;

    public function __construct(
        #[ORM\Column(length: 64)]
        private string $code,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 16, enumType: AttributeType::class)]
        private AttributeType $type,
    ) {
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): AttributeType
    {
        return $this->type;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function isFilterable(): bool
    {
        return $this->isFilterable;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** @return Collection<int, AttributeOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function update(string $code, string $name, AttributeType $type, ?string $unit, bool $isFilterable, int $position): void
    {
        if ($type !== $this->type && !$type->usesOptions()) {
            $this->options->clear();
        }
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->unit = $unit;
        $this->isFilterable = $isFilterable;
        $this->position = $position;
    }

    public function findOption(string $value): ?AttributeOption
    {
        foreach ($this->options as $option) {
            if ($option->getValue() === $value) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Keeps options whose value is still listed (so product values stay linked), adds new ones,
     * removes the rest, and applies the new order.
     *
     * @param list<string> $values
     */
    public function replaceOptions(array $values): void
    {
        foreach ($this->options->toArray() as $option) {
            if (!\in_array($option->getValue(), $values, true)) {
                $this->options->removeElement($option);
            }
        }
        foreach ($values as $position => $value) {
            $option = $this->findOption($value);
            if (null === $option) {
                $option = new AttributeOption($this, $value);
                $this->options->add($option);
            }
            $option->moveTo($position);
        }
    }
}
