<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Exception;

final class StoreAccessDeniedException extends \RuntimeException
{
    public static function forStore(string $storeCode): self
    {
        return new self(sprintf('You do not have access to store "%s".', $storeCode));
    }

    public static function forAllStores(): self
    {
        return new self('Only super-admins can view all stores.');
    }
}
