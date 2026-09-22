<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Payment\PaymentState;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant table: one payment attempt for an order. An order can have several attempts; the state
 * is the payment workflow marking.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment')]
#[ORM\Index(name: 'idx_payment_gateway_reference', columns: ['gateway_code', 'external_reference'])]
#[ORM\HasLifecycleCallbacks]
class Payment implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 32)]
    private string $state = PaymentState::Pending->value;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $checkoutUrl = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\Column(length: 32)]
        private string $gatewayCode,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $amount,
        #[ORM\Column(length: 3, options: ['fixed' => true])]
        private string $currencyCode,
    ) {
        $this->publicId = Uuid::v7();
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

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getGatewayCode(): string
    {
        return $this->gatewayCode;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
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

    public function state(): PaymentState
    {
        return PaymentState::from($this->state);
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function attachSession(string $externalReference, string $checkoutUrl, array $metadata = []): void
    {
        $this->externalReference = $externalReference;
        $this->checkoutUrl = $checkoutUrl;
        $this->metadata = $metadata + $this->metadata;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
