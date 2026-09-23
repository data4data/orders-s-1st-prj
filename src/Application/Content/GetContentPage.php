<?php

declare(strict_types=1);

namespace App\Application\Content;

/**
 * Query: data for the shipping info and safety data sheet pages.
 */
final readonly class GetContentPage
{
    public function __construct(public string $page)
    {
    }
}
