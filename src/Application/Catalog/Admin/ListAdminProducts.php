<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

/** Result: array{items: list<array<string, mixed>>, total: int}. */
final readonly class ListAdminProducts
{
    public function __construct(public string $search = '', public int $page = 1, public int $perPage = 20)
    {
    }
}
