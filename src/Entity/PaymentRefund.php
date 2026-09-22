<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: money sent back for a captured payment. Partial refunds are separate rows; the
 * payment becomes "refunded" once they add up to its amount.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment_refund')]
class PaymentRefund implements TenantAwareInterface
{
    use TenantAwareTrait;

    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'refunds')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Payment $payment,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $amount,
        #[ORM\Column(length: 255)]
        private string $reason,
        #[ORM\Column(length: 16)]
        private string $state,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $externalReference = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function isSucceeded(): bool
    {
        return self::SUCCEEDED === $this->state;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
