<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Domain\Ordering\ActorType;

/**
 * Who is changing an order right now (staff user, customer, or the system: webhooks, scheduler).
 */
final readonly class OrderActor
{
    public function __construct(
        public ActorType $type,
        public ?int $id = null,
        public ?string $name = null,
    ) {
    }
}
