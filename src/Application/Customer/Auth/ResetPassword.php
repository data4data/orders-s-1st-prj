<?php

declare(strict_types=1);

namespace App\Application\Customer\Auth;

use App\Application\Customer\Input\ResetPasswordInput;

/**
 * Sets a new password from a reset link and logs the customer in.
 */
final readonly class ResetPassword
{
    public function __construct(public string $token, public ResetPasswordInput $input)
    {
    }
}
