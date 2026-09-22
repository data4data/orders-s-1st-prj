<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a product image as an https link (decision #39). With a variant it is shown only
 * for that pack size.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product_image')]
class ProductImage implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
        #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(length: 500)]
        private string $url,
        #[ORM\Column(length: 200)]
        private string $altText,
        #[ORM\Column]
        private int $position = 0,
        #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
        #[ORM\JoinColumn(name: 'product_variant_id', nullable: true, onDelete: 'SET NULL')]
        private ?ProductVariant $variant = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAltText(): string
    {
        return $this->altText;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }
}
