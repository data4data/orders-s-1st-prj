<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Exception;

final class StoreNotFoundException extends \RuntimeException
{
    public static function withPublicId(string $publicId): self
    {
        return new self(sprintf('Store "%s" does not exist.', $publicId));
    }
}
