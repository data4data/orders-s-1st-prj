<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant table: a lubricant product; it is sold as pack-size variants (decision #13).
 * `version` is an optimistic lock: saving a stale edit fails with 409 (decision: conflict dialog).
 */
#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uniq_product_store_slug', columns: ['store_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Product implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 80)]
    private string $brand = "MyOil's";

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'product_category')]
    #[ORM\JoinColumn(name: 'product_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', onDelete: 'CASCADE')]
    private Collection $categories;

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['volumeMl' => 'ASC'])]
    private Collection $variants;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, ProductDocument> */
    #[ORM\OneToMany(targetEntity: ProductDocument::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $documents;

    /** @var Collection<int, ProductAttributeValue> */
    #[ORM\OneToMany(targetEntity: ProductAttributeValue::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private Collection $attributeValues;

    public function __construct(
        #[ORM\Column(length: 160)]
        private string $slug,
        #[ORM\Column(length: 160)]
        private string $name,
        #[ORM\ManyToOne(targetEntity: TaxCategory::class)]
        #[ORM\JoinColumn(name: 'tax_category_id', nullable: false)]
        private TaxCategory $taxCategory,
    ) {
        $this->publicId = Uuid::v7();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->categories = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->attributeValues = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTaxCategory(): TaxCategory
    {
        return $this->taxCategory;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateGeneral(string $slug, string $name, string $brand, ?string $description, TaxCategory $taxCategory, bool $isActive): void
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->brand = $brand;
        $this->description = $description;
        $this->taxCategory = $taxCategory;
        $this->isActive = $isActive;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /**
     * @param list<Category> $categories
     */
    public function replaceCategories(array $categories): void
    {
        $this->categories->clear();
        foreach ($categories as $category) {
            $this->categories->add($category);
        }
    }

    /** @return Collection<int, ProductVariant> */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    /** @return list<ProductVariant> */
    public function activeVariants(): array
    {
        return array_values($this->variants->filter(static fn (ProductVariant $v): bool => $v->isActive())->toArray());
    }

    public function addVariant(ProductVariant $variant): void
    {
        $this->variants->add($variant);
    }

    public function removeVariant(ProductVariant $variant): void
    {
        $this->variants->removeElement($variant);
    }

    public function findVariant(string $publicId): ?ProductVariant
    {
        foreach ($this->variants as $variant) {
            if ($variant->getPublicId()->toRfc4122() === $publicId) {
                return $variant;
            }
        }

        return null;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    /**
     * @param list<ProductImage> $images
     */
    public function replaceImages(array $images): void
    {
        $this->images->clear();
        foreach ($images as $image) {
            $this->images->add($image);
        }
    }

    /** @return Collection<int, ProductDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    /**
     * @param list<ProductDocument> $documents
     */
    public function replaceDocuments(array $documents): void
    {
        $this->documents->clear();
        foreach ($documents as $document) {
            $this->documents->add($document);
        }
    }

    /** @return Collection<int, ProductAttributeValue> */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    /**
     * @param list<ProductAttributeValue> $values
     */
    public function replaceAttributeValues(array $values): void
    {
        $this->attributeValues->clear();
        foreach ($values as $value) {
            $this->attributeValues->add($value);
        }
    }
}
