<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Something the request asked for does not exist in the current store. Answered as 404.
 */
final class NotFoundException extends \RuntimeException
{
    public static function of(string $what, string $identifier): self
    {
        return new self(sprintf('%s "%s" does not exist.', $what, $identifier));
    }
}
