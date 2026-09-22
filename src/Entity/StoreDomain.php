<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Platform table: a host name that leads to a store (for example myoils-auto.shop.test).
 * It is read before the store is known, so it is deliberately not tenant-filtered.
 */
#[ORM\Entity]
#[ORM\Table(name: 'store_domain')]
class StoreDomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 190, unique: true)]
    private string $host;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Store::class)]
        #[ORM\JoinColumn(name: 'store_id', nullable: false, onDelete: 'CASCADE')]
        private Store $store,
        string $host,
        #[ORM\Column]
        private bool $isPrimary = false,
    ) {
        $host = strtolower(trim($host));
        if (str_starts_with($host, 'admin.')) {
            throw new \InvalidArgumentException('Host names starting with "admin." are reserved for the admin.');
        }
        $this->host = $host;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function markPrimary(bool $primary): void
    {
        $this->isPrimary = $primary;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}
