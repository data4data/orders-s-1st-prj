<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

final readonly class DeleteProduct
{
    public function __construct(public string $publicId)
    {
    }
}
