<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

final readonly class DeleteCategory
{
    public function __construct(public int $id)
    {
    }
}
