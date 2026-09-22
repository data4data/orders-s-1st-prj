<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

/**
 * Query: placed orders of the logged-in customer, newest first.
 */
final readonly class ListMyOrders
{
    public function __construct(public int $page = 1)
    {
    }
}
