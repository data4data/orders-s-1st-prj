<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI;

use App\UI\Theme\BrandPalette;
use PHPUnit\Framework\TestCase;

final class BrandPaletteTest extends TestCase
{
    public function testGeneratesShadesAndReadableTextColour(): void
    {
        $vars = BrandPalette::cssVariables('#0F2742', '#F2A900');

        self::assertSame('#0F2742', $vars['--brand-primary']);
        self::assertSame('#0F2742', $vars['--brand-primary-500']);
        self::assertSame('15, 39, 66', $vars['--brand-primary-rgb']);
        self::assertSame('#F3F4F6', $vars['--brand-primary-50']);
        self::assertSame('#FFFFFF', $vars['--brand-on-primary'], 'white text on navy');
        self::assertSame('#1C2330', $vars['--brand-on-accent'], 'dark text on amber');
        self::assertCount(2 * 14, $vars);
    }

    public function testInvalidColoursFallBackToThePlatformPalette(): void
    {
        $vars = BrandPalette::cssVariables('red; } body { display: none', null);

        self::assertSame(BrandPalette::PLATFORM_PRIMARY, $vars['--brand-primary']);
        self::assertSame(BrandPalette::PLATFORM_ACCENT, $vars['--brand-accent']);
    }
}
