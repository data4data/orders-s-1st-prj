<?php

declare(strict_types=1);

namespace App\Application\Catalog\Port;

use App\Entity\TaxCategory;

interface TaxCategoryRepositoryInterface
{
    public function findById(int $id): ?TaxCategory;

    public function findByCode(string $code): ?TaxCategory;

    /** @return list<TaxCategory> */
    public function findAll(): array;
}
