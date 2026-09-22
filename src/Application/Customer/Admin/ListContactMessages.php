<?php

declare(strict_types=1);

namespace App\Application\Customer\Admin;

/**
 * Query: contact form messages of the selected store, newest first.
 */
final readonly class ListContactMessages
{
    public function __construct(public bool $unreadOnly = false, public int $page = 1)
    {
    }
}
