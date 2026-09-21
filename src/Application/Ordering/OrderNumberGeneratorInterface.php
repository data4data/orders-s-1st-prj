<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Entity\Store;

/**
 * Hands out gap-tolerant, per-store order numbers such as "AUTO-000124".
 */
interface OrderNumberGeneratorInterface
{
    public function next(Store $store): string;
}
