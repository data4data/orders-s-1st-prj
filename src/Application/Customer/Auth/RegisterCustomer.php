<?php

declare(strict_types=1);

namespace App\Application\Customer\Auth;

use App\Application\Customer\Input\RegisterCustomerInput;

/**
 * Creates a customer account with its billing (and delivery) address, then logs the customer in.
 */
final readonly class RegisterCustomer
{
    public function __construct(public RegisterCustomerInput $input)
    {
    }
}
