<?php

declare(strict_types=1);

namespace App\Application\Customer\Admin;

/**
 * Marks a contact message as read.
 */
final readonly class MarkContactMessageRead
{
    public function __construct(public int $id)
    {
    }
}
