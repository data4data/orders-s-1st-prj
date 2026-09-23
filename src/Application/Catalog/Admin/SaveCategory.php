<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

use App\Application\Catalog\Input\CategoryInput;

/** Result: int (category id). */
final readonly class SaveCategory
{
    public function __construct(public ?int $id, public CategoryInput $input)
    {
    }
}
