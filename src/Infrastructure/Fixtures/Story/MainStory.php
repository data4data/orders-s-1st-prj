<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Story;

use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class MainStory extends Story
{
    public function build(): void
    {
        // Demo stores, catalog, customers and orders are added in Phase 8.
    }
}
