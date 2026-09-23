<?php

declare(strict_types=1);

namespace App\Application\Customer\Auth;

/**
 * Emails a reset link when the address belongs to a customer of this shop (and says nothing otherwise).
 */
final readonly class RequestPasswordReset
{
    public function __construct(public string $email)
    {
    }
}
