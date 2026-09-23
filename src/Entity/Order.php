<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Ordering\OrderState;
use App\Domain\Pricing\OrderTotals;
use App\Domain\Shared\Quantity;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use App\Entity\Embeddable\PostalAddress;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant table `orders`: the draft order is the cart (decision #18); `checkout` turns it into a
 * real order with a number, snapshots and reserved stock. The state is the order workflow marking.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'uniq_order_store_number', columns: ['store_id', 'order_number'])]
#[ORM\Index(name: 'idx_order_store_state', columns: ['store_id', 'state', 'placed_at'])]
#[ORM\Index(name: 'idx_order_store_customer', columns: ['store_id', 'customer_id', 'state'])]
#[ORM\HasLifecycleCallbacks]
class Order implements TenantAwareInterface
{
    use TenantAwareTrait;

    /** Largest quantity of one pack size in one order (keeps typos like 1000 drums out). */
    public const MAX_QUANTITY = 99;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 32)]
    private string $state = OrderState::Draft->value;

    #[ORM\ManyToOne(targetEntity: Coupon::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Coupon $coupon = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $couponCode = null;

    #[ORM\ManyToOne(targetEntity: ShippingMethod::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ShippingMethod $shippingMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingMethodName = null;

    #[ORM\Column(length: 2, nullable: true, options: ['fixed' => true])]
    private ?string $taxCountryCode = null;

    #[ORM\Column]
    private int $itemsNet = 0;

    #[ORM\Column]
    private int $discountNet = 0;

    #[ORM\Column]
    private int $shippingNet = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $shippingTaxRate = null;

    #[ORM\Column]
    private int $totalNet = 0;

    #[ORM\Column]
    private int $totalTax = 0;

    #[ORM\Column]
    private int $totalGross = 0;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: 'billing_')]
    private PostalAddress $billingAddress;

    #[ORM\Embedded(class: PostalAddress::class, columnPrefix: 'shipping_')]
    private PostalAddress $shippingAddress;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $placedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    public function __construct(
        #[ORM\Column(length: 3, options: ['fixed' => true])]
        private string $currencyCode,
    ) {
        $this->publicId = Uuid::v7();
        $this->items = new ArrayCollection();
        $this->billingAddress = new PostalAddress();
        $this->shippingAddress = new PostalAddress();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
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

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    /** Workflow marking (method marking store). */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setState(string $state, array $context = []): void
    {
        $this->state = $state;
    }

    public function state(): OrderState
    {
        return OrderState::from($this->state);
    }

    public function isDraft(): bool
    {
        return OrderState::Draft === $this->state();
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function assignCustomer(?Customer $customer): void
    {
        $this->assertDraft();
        $this->customer = $customer;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function itemCount(): int
    {
        return array_sum($this->items->map(static fn (OrderItem $item) => $item->getQuantity())->toArray());
    }

    public function findItem(string $variantPublicId): ?OrderItem
    {
        foreach ($this->items as $item) {
            if ($item->getVariant()?->getPublicId()->toRfc4122() === $variantPublicId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Adds a pack size to the cart, or raises the quantity when it is already there.
     */
    public function add(ProductVariant $variant, Quantity $quantity): OrderItem
    {
        $this->assertDraft();
        $item = $this->findItem($variant->getPublicId()->toRfc4122());
        if (null === $item) {
            $item = new OrderItem($this, $variant, $quantity->value);
            $this->items->add($item);
        } else {
            $item->changeQuantity(min(self::MAX_QUANTITY, $item->getQuantity() + $quantity->value));
        }

        return $item;
    }

    public function remove(OrderItem $item): void
    {
        $this->assertDraft();
        $this->items->removeElement($item);
    }

    public function getCoupon(): ?Coupon
    {
        return $this->coupon;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function applyCoupon(?Coupon $coupon): void
    {
        $this->assertDraft();
        $this->coupon = $coupon;
        $this->couponCode = $coupon?->getCode();
    }

    public function getShippingMethod(): ?ShippingMethod
    {
        return $this->shippingMethod;
    }

    public function getShippingMethodName(): ?string
    {
        return $this->shippingMethodName;
    }

    public function getTaxCountryCode(): ?string
    {
        return $this->taxCountryCode;
    }

    public function getBillingAddress(): PostalAddress
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): PostalAddress
    {
        return $this->shippingAddress;
    }

    public function getItemsNet(): int
    {
        return $this->itemsNet;
    }

    public function getDiscountNet(): int
    {
        return $this->discountNet;
    }

    public function getShippingNet(): int
    {
        return $this->shippingNet;
    }

    public function getShippingTaxRate(): ?string
    {
        return $this->shippingTaxRate;
    }

    public function getTotalNet(): int
    {
        return $this->totalNet;
    }

    public function getTotalTax(): int
    {
        return $this->totalTax;
    }

    public function getTotalGross(): int
    {
        return $this->totalGross;
    }

    public function getPlacedAt(): ?\DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Freezes everything the order depends on (decision: snapshots). Called by PlaceOrder in the
     * same transaction as the `checkout` transition; the line snapshots are set on each item.
     */
    public function snapshot(
        string $orderNumber,
        string $customerEmail,
        PostalAddress $billing,
        PostalAddress $shipping,
        ShippingMethod $shippingMethod,
        string $shippingTaxRate,
        string $taxCountryCode,
        OrderTotals $totals,
        \DateTimeImmutable $placedAt,
    ): void {
        $this->assertDraft();
        $this->orderNumber = $orderNumber;
        $this->customerEmail = Customer::normalizeEmail($customerEmail);
        $this->billingAddress = $billing;
        $this->shippingAddress = $shipping;
        $this->shippingMethod = $shippingMethod;
        $this->shippingMethodName = $shippingMethod->getName();
        $this->shippingTaxRate = $shippingTaxRate;
        $this->taxCountryCode = $taxCountryCode;
        $this->itemsNet = $totals->itemsNet->amount;
        $this->discountNet = $totals->discountNet->amount;
        $this->shippingNet = $totals->shippingNet->amount;
        $this->totalNet = $totals->totalNet->amount;
        $this->totalTax = $totals->totalTax->amount;
        $this->totalGross = $totals->totalGross->amount;
        $this->placedAt = $placedAt;
    }

    private function assertDraft(): void
    {
        if (!$this->isDraft()) {
            throw new \DomainException('This order has been placed and can no longer be changed.');
        }
    }
}
