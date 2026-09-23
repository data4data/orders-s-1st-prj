<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Entity\Customer;

/**
 * Single-use, expiring password reset links. A token stops working as soon as the password changes.
 */
interface PasswordResetTokensInterface
{
    public function create(Customer $customer): string;

    /** The customer the token belongs to, or null when it is invalid, used or expired. */
    public function verify(string $token): ?Customer;
}
