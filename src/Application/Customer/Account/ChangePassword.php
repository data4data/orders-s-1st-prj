<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

use App\Application\Customer\Input\ChangePasswordInput;

/**
 * Changes the password after checking the current one.
 */
final readonly class ChangePassword
{
    public function __construct(public ChangePasswordInput $input)
    {
    }
}
