<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

use App\Application\Customer\Input\ProfileInput;

/**
 * Changes name and phone.
 */
final readonly class UpdateProfile
{
    public function __construct(public ProfileInput $input)
    {
    }
}
