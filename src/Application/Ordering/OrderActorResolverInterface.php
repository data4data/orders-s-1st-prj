<?php

declare(strict_types=1);

namespace App\Application\Ordering;

interface OrderActorResolverInterface
{
    public function current(): OrderActor;
}
