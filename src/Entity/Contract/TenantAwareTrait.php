<?php

declare(strict_types=1);

namespace App\Entity\Contract;

use App\Entity\Store;
use Doctrine\ORM\Mapping as ORM;

/**
 * Default `store` association for tenant entities (column `store_id`).
 */
trait TenantAwareTrait
{
    #[ORM\ManyToOne(targetEntity: Store::class)]
    #[ORM\JoinColumn(name: 'store_id', nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function assignStore(Store $store): void
    {
        if (null !== $this->store && $this->store !== $store) {
            throw new \LogicException(sprintf('%s already belongs to store "%s".', static::class, $this->store->getCode()));
        }
        $this->store = $store;
    }
}
