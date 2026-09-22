<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Ordering\ActorType;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: the audit trail of an order, one row per completed workflow transition.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_status_history')]
#[ORM\Index(name: 'idx_history_order', columns: ['order_id', 'created_at'])]
class OrderStatusHistory implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\Column(length: 32)]
        private string $transition,
        #[ORM\Column(length: 32)]
        private string $fromState,
        #[ORM\Column(length: 32)]
        private string $toState,
        #[ORM\Column(length: 16, enumType: ActorType::class)]
        private ActorType $actorType,
        #[ORM\Column(nullable: true, options: ['unsigned' => true])]
        private ?int $actorId = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $actorName = null,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $comment = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): string
    {
        return $this->toState;
    }

    public function getActorType(): ActorType
    {
        return $this->actorType;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
