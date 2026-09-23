<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Entity\Customer;

/**
 * The logged-in shopper of the current store, if any.
 */
interface CurrentCustomerInterface
{
    public function get(): ?Customer;
}
