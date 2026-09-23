<?php

declare(strict_types=1);

namespace App\Application\Customer\Auth;

/**
 * Query: is this reset link still valid? (The page shows the form or "link expired".).
 */
final readonly class CheckPasswordResetToken
{
    public function __construct(public string $token)
    {
    }
}
