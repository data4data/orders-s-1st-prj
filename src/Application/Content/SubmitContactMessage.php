<?php

declare(strict_types=1);

namespace App\Application\Content;

/**
 * Stores a contact form message and notifies the shop by email.
 */
final readonly class SubmitContactMessage
{
    public function __construct(public ContactInput $input)
    {
    }
}
