<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

use App\Application\Catalog\Input\ProductInput;

/** Result: string (product public id). */
final readonly class SaveProduct
{
    public function __construct(public ?string $publicId, public ProductInput $input)
    {
    }
}
