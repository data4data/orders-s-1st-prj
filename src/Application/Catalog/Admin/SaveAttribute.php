<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

use App\Application\Catalog\Input\AttributeInput;

/** Result: int (attribute id). */
final readonly class SaveAttribute
{
    public function __construct(public ?int $id, public AttributeInput $input)
    {
    }
}
