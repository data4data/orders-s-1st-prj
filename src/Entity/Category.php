<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a category in the store's tree (parent_id NULL = top level, shown in the header).
 */
#[ORM\Entity]
#[ORM\Table(name: 'category')]
#[ORM\UniqueConstraint(name: 'uniq_category_store_slug', columns: ['store_id', 'slug'])]
#[ORM\Index(name: 'idx_category_store_parent', columns: ['store_id', 'parent_id', 'position'])]
class Category implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $slug,
        #[ORM\Column(length: 120)]
        private string $name,
    ) {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /** @return Collection<int, Category> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function update(string $slug, string $name, ?string $description, int $position, bool $isActive): void
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->description = $description;
        $this->position = $position;
        $this->isActive = $isActive;
    }

    public function moveTo(?self $parent): void
    {
        for ($ancestor = $parent; null !== $ancestor; $ancestor = $ancestor->getParent()) {
            if ($ancestor === $this) {
                throw new \DomainException('A category cannot be placed inside itself.');
            }
        }
        // Keep both sides of the tree in sync so an in-memory tree is complete before a reload.
        $this->parent?->children->removeElement($this);
        $this->parent = $parent;
        if (null !== $parent && !$parent->children->contains($this)) {
            $parent->children->add($this);
        }
    }

    /**
     * This category and every category below it (for "products in this category").
     *
     * @return list<self>
     */
    public function withDescendants(): array
    {
        $all = [$this];
        foreach ($this->children as $child) {
            array_push($all, ...$child->withDescendants());
        }

        return $all;
    }

    /**
     * Top-level first, e.g. [Engine oil, Passenger car].
     *
     * @return list<self>
     */
    public function path(): array
    {
        $path = [];
        for ($node = $this; null !== $node; $node = $node->getParent()) {
            array_unshift($path, $node);
        }

        return $path;
    }
}
