<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

/**
 * Query: one order with payments, history and the actions allowed now.
 */
final readonly class GetAdminOrder
{
    public function __construct(public string $id)
    {
    }
}
