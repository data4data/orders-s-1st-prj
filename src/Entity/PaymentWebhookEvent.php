<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a verified gateway callback, stored before processing. The unique key
 * (gateway_code, external_event_id) makes a repeated delivery a no-op.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment_webhook_event')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_gateway_event', columns: ['gateway_code', 'external_event_id'])]
class PaymentWebhookEvent implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $result = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array{type: string, reference: string, amount: ?int, raw: string} $payload
     */
    public function __construct(
        #[ORM\Column(length: 32)]
        private string $gatewayCode,
        #[ORM\Column(length: 120)]
        private string $externalEventId,
        #[ORM\Column(type: 'json')]
        private array $payload,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGatewayCode(): string
    {
        return $this->gatewayCode;
    }

    public function getExternalEventId(): string
    {
        return $this->externalEventId;
    }

    /**
     * @return array{type: string, reference: string, amount: ?int, raw: string}
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function isProcessed(): bool
    {
        return null !== $this->processedAt;
    }

    public function markProcessed(string $result, \DateTimeImmutable $at): void
    {
        $this->processedAt = $at;
        $this->result = $result;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
