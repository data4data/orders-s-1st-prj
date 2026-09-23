<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Entity\ShippingMethod;

interface ShippingMethodRepositoryInterface
{
    /** @return list<ShippingMethod> active methods of the store, in position order */
    public function findActive(): array;

    public function findActiveByCode(string $code): ?ShippingMethod;
}
