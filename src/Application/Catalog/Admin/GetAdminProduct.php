<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

/** Result: array<string, mixed> (the admin product form). */
final readonly class GetAdminProduct
{
    public function __construct(public string $publicId)
    {
    }
}
