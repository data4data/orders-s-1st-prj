<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: a message sent through the contact form of a shop (Admin → Customers → Contact messages).
 */
#[ORM\Entity]
#[ORM\Table(name: 'contact_message')]
#[ORM\Index(name: 'idx_contact_store_created', columns: ['store_id', 'created_at'])]
class ContactMessage implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 40)]
        private string $subject,
        #[ORM\Column(type: 'text')]
        private string $message,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $orderNumber = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(\DateTimeImmutable $at): void
    {
        $this->readAt ??= $at;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
