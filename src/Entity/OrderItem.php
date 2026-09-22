<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Pricing\PricedLine;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: one line of an order. In the cart only variant and quantity matter (prices are
 * live); at checkout SKU, names, net price, VAT rate and line totals are frozen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_item')]
class OrderItem implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'product_variant_id', nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $variant;

    #[ORM\Column(length: 64)]
    private string $sku;

    #[ORM\Column(length: 160)]
    private string $productName;

    #[ORM\Column(length: 80)]
    private string $variantName;

    #[ORM\Column]
    private int $unitPriceNet = 0;

    #[ORM\Column]
    private int $discountNet = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $taxRate = null;

    #[ORM\Column]
    private int $lineNet = 0;

    #[ORM\Column]
    private int $lineTax = 0;

    #[ORM\Column]
    private int $lineGross = 0;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        ProductVariant $variant,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $quantity,
    ) {
        $this->variant = $variant;
        $this->sku = $variant->getSku();
        $this->productName = $variant->getProduct()->getName();
        $this->variantName = $variant->getName();
        $this->changeQuantity($quantity);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getVariantName(): string
    {
        return $this->variantName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function changeQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > Order::MAX_QUANTITY) {
            throw new \DomainException(sprintf('The quantity must be between 1 and %d.', Order::MAX_QUANTITY));
        }
        $this->quantity = $quantity;
    }

    public function getUnitPriceNet(): int
    {
        return $this->unitPriceNet;
    }

    public function getDiscountNet(): int
    {
        return $this->discountNet;
    }

    public function getTaxRate(): ?string
    {
        return $this->taxRate;
    }

    public function getLineNet(): int
    {
        return $this->lineNet;
    }

    public function getLineTax(): int
    {
        return $this->lineTax;
    }

    public function getLineGross(): int
    {
        return $this->lineGross;
    }

    /**
     * Freezes the line at checkout: current SKU and names plus the priced line from the domain.
     */
    public function snapshot(PricedLine $line): void
    {
        if (null !== $this->variant) {
            $this->sku = $this->variant->getSku();
            $this->productName = $this->variant->getProduct()->getName();
            $this->variantName = $this->variant->getName();
        }
        $this->unitPriceNet = $line->unitNet->amount;
        $this->discountNet = $line->discountNet->amount;
        $this->taxRate = $line->taxRate->toString();
        $this->lineNet = $line->net->amount;
        $this->lineTax = $line->tax->amount;
        $this->lineGross = $line->gross->amount;
    }
}
