<?php

declare(strict_types=1);

namespace App\Application\Customer\Port;

use App\Entity\Customer;
use Symfony\Component\Uid\Uuid;

interface CustomerRepositoryInterface
{
    /** In the current store (tenant filter). */
    public function findByEmail(string $email): ?Customer;

    public function findByPublicId(Uuid $publicId): ?Customer;

    public function save(Customer $customer): void;
}
