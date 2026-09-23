<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * Who triggers an order transition (order_status_history.actor_type).
 */
enum ActorType: string
{
    case Staff = 'staff';
    case Customer = 'customer';
    case System = 'system';
}
