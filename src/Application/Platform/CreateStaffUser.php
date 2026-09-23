<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Creates a global staff user.
 */
final readonly class CreateStaffUser
{
    public function __construct(public StaffUserInput $input)
    {
    }
}
