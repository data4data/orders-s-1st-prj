<?php

declare(strict_types=1);

namespace App\Entity\Contract;

use App\Entity\Store;

/**
 * Marks an entity whose table has a non-null `store_id` column.
 *
 * Every query on such an entity is scoped to the active store by the Doctrine TenantFilter,
 * and new instances get their store assigned automatically on persist.
 */
interface TenantAwareInterface
{
    public function getStore(): ?Store;

    public function assignStore(Store $store): void;
}
