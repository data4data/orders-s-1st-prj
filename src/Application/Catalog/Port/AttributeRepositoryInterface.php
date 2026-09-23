<?php

declare(strict_types=1);

namespace App\Application\Catalog\Port;

use App\Entity\Attribute;

interface AttributeRepositoryInterface
{
    public function findById(int $id): ?Attribute;

    /** @return list<Attribute> ordered by position, with options */
    public function findAllOrdered(): array;

    public function codeExists(string $code, ?int $exceptId = null): bool;

    public function countProductsUsing(Attribute $attribute): int;

    public function save(Attribute $attribute): void;

    public function remove(Attribute $attribute): void;
}
