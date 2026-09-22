<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Catalog\DocumentType;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a safety data sheet, technical data sheet or approval letter, as an https link.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product_document')]
class ProductDocument implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'documents')]
        #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(length: 16, enumType: DocumentType::class)]
        private DocumentType $type,
        #[ORM\Column(length: 200)]
        private string $title,
        #[ORM\Column(length: 500)]
        private string $url,
        #[ORM\Column(length: 10)]
        private string $locale = 'en',
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
