<?php

declare(strict_types=1);

namespace App\Application\Catalog;

/**
 * Pack size labels: 1000 ml -> "1 L", 208000 ml -> "208 L", 500 ml -> "500 ml".
 */
final class PackSize
{
    public static function label(int $volumeMl): string
    {
        if ($volumeMl >= 1000) {
            return rtrim(rtrim(number_format($volumeMl / 1000, 2, '.', ''), '0'), '.').' L';
        }

        return $volumeMl.' ml';
    }
}
