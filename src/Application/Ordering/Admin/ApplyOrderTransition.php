<?php

declare(strict_types=1);

namespace App\Application\Ordering\Admin;

/**
 * Staff apply a workflow transition (the version guards against acting on a stale screen).
 */
final readonly class ApplyOrderTransition
{
    public function __construct(public string $id, public string $transition, public ?string $comment = null, public ?int $version = null)
    {
    }
}
