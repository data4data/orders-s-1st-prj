<?php

declare(strict_types=1);

namespace App\Application\Customer\Port;

use App\Entity\Country;

interface CountryRepositoryInterface
{
    public function findByCode(string $code): ?Country;

    /** @return list<Country> sorted by name */
    public function findAll(): array;
}
