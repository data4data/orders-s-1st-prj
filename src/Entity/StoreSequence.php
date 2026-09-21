<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contract\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant table: per-store counters such as the order number.
 * Values are claimed with SELECT … FOR UPDATE by the OrderNumberGenerator.
 */
#[ORM\Entity]
#[ORM\Table(name: 'store_sequence')]
class StoreSequence implements TenantAwareInterface
{
    #[ORM\Column(options: ['unsigned' => true])]
    private int $nextValue = 1;

    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Store::class)]
        #[ORM\JoinColumn(name: 'store_id', nullable: false, onDelete: 'CASCADE')]
        private Store $store,
        #[ORM\Id]
        #[ORM\Column(length: 32)]
        private string $name,
    ) {
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function assignStore(Store $store): void
    {
        if ($store !== $this->store) {
            throw new \LogicException('A store sequence cannot move to another store.');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNextValue(): int
    {
        return $this->nextValue;
    }
}
