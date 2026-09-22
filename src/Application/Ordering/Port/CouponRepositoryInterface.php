<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Entity\Coupon;

interface CouponRepositoryInterface
{
    public function findByCode(string $code): ?Coupon;
}
