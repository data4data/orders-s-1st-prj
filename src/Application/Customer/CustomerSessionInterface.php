<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Entity\Customer;

/**
 * Logs a customer in on the storefront (right after registration or a password reset).
 */
interface CustomerSessionInterface
{
    public function logIn(Customer $customer): void;
}
