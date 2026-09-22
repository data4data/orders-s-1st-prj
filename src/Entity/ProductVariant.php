<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Inventory\StockLevel;
use App\Domain\Money\Money;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant table: one pack size of a product (1 L, 5 L, 208 L drum…) with its own SKU, net price
 * and stock (on_hand + reserved, decision #14).
 */
#[ORM\Entity]
#[ORM\Table(name: 'product_variant')]
#[ORM\UniqueConstraint(name: 'uniq_variant_store_sku', columns: ['store_id', 'sku'])]
class ProductVariant implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $onHand = 0;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $reserved = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
        #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(length: 64)]
        private string $sku,
        #[ORM\Column(length: 60)]
        private string $name,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $volumeMl,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $weightG,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $priceNet,
    ) {
        $this->publicId = Uuid::v7();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVolumeMl(): int
    {
        return $this->volumeMl;
    }

    public function getWeightG(): int
    {
        return $this->weightG;
    }

    /** Net price in cents of the store currency. */
    public function getPriceNet(): int
    {
        return $this->priceNet;
    }

    public function priceNet(string $currency): Money
    {
        return Money::of($this->priceNet, $currency);
    }

    public function stock(): StockLevel
    {
        return new StockLevel($this->onHand, $this->reserved);
    }

    public function getOnHand(): int
    {
        return $this->onHand;
    }

    public function getReserved(): int
    {
        return $this->reserved;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function update(string $sku, string $name, int $volumeMl, int $weightG, int $priceNet, bool $isActive): void
    {
        $this->sku = $sku;
        $this->name = $name;
        $this->volumeMl = $volumeMl;
        $this->weightG = $weightG;
        $this->priceNet = $priceNet;
        $this->isActive = $isActive;
    }

    /**
     * Sets the physical stock count; it can never drop below what open orders have reserved.
     */
    public function setOnHand(int $onHand): void
    {
        $this->applyStock(new StockLevel($onHand, $this->reserved));
    }

    public function applyStock(StockLevel $stock): void
    {
        $this->onHand = $stock->onHand;
        $this->reserved = $stock->reserved;
    }
}
