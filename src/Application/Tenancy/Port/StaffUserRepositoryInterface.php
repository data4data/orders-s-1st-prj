<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

use App\Entity\StaffUser;

interface StaffUserRepositoryInterface
{
    public function findByEmail(string $email): ?StaffUser;
}
