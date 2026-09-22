<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Switches a shop on or off (an inactive shop answers Store not found).
 */
final readonly class SetStoreActive
{
    public function __construct(public int $id, public bool $active)
    {
    }
}
