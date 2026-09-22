<?php

declare(strict_types=1);

namespace App\UI\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * {{ cents|money('EUR') }} → "€1,489.00", the same format as assets/shared/format.js formatMoney().
 */
final class MoneyExtension
{
    private const SYMBOLS = ['EUR' => '€', 'GBP' => '£', 'USD' => '$'];

    #[AsTwigFilter('money')]
    public function money(int $cents, string $currency): string
    {
        $amount = number_format(abs($cents) / 100, 2, '.', ',');
        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';

        return ($cents < 0 ? '-' : '').$symbol.$amount;
    }
}
