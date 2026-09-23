<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Creates a shop with its first host name.
 */
final readonly class CreateStore
{
    public function __construct(public StoreInput $input)
    {
    }
}
