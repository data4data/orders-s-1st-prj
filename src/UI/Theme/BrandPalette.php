<?php

declare(strict_types=1);

namespace App\UI\Theme;

/**
 * Turns a store's two brand colours into CSS variables with generated shades
 * (--brand-primary, --brand-primary-50 … -950, --brand-primary-rgb, --brand-on-primary, same for
 * accent). Both frontends read only these variables (architecture.md §8, per-store branding).
 */
final class BrandPalette
{
    /** Neutral platform look: admin, and pages without a store (decision #43). */
    public const PLATFORM_PRIMARY = '#2563EB';
    public const PLATFORM_ACCENT = '#64748B';

    /** Shade => [mix colour, weight of the mix colour]. */
    private const SHADES = [
        50 => [[255, 255, 255], 0.95], 100 => [[255, 255, 255], 0.90], 200 => [[255, 255, 255], 0.75],
        300 => [[255, 255, 255], 0.60], 400 => [[255, 255, 255], 0.30], 500 => [[0, 0, 0], 0.0],
        600 => [[0, 0, 0], 0.15], 700 => [[0, 0, 0], 0.30], 800 => [[0, 0, 0], 0.45],
        900 => [[0, 0, 0], 0.60], 950 => [[0, 0, 0], 0.75],
    ];

    /**
     * @return array<string, string> CSS custom property => value
     */
    public static function cssVariables(?string $primary, ?string $accent): array
    {
        return self::forColour('primary', self::valid($primary) ?? self::PLATFORM_PRIMARY)
            + self::forColour('accent', self::valid($accent) ?? self::PLATFORM_ACCENT);
    }

    /**
     * @return array<string, string>
     */
    private static function forColour(string $name, string $hex): array
    {
        $rgb = self::toRgb($hex);
        $vars = [
            "--brand-{$name}" => strtoupper($hex),
            "--brand-{$name}-rgb" => implode(', ', $rgb),
            "--brand-on-{$name}" => self::readableTextOn($rgb),
        ];
        foreach (self::SHADES as $shade => [$mix, $weight]) {
            $vars["--brand-{$name}-{$shade}"] = self::toHex(array_map(
                static fn (int $channel, int $target): int => (int) round($channel * (1 - $weight) + $target * $weight),
                $rgb,
                $mix,
            ));
        }

        return $vars;
    }

    /** Only #RRGGBB is accepted: colours come from store settings and end up in a <style> tag. */
    private static function valid(?string $hex): ?string
    {
        return null !== $hex && 1 === preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? $hex : null;
    }

    /**
     * @return array{int, int, int}
     */
    private static function toRgb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /**
     * @param list<int> $rgb
     */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', ...$rgb);
    }

    /**
     * White or near-black text, whichever contrasts more (WCAG relative luminance).
     *
     * @param array{int, int, int} $rgb
     */
    private static function readableTextOn(array $rgb): string
    {
        $linear = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];

        $contrastWithWhite = 1.05 / ($luminance + 0.05);
        $contrastWithDark = ($luminance + 0.05) / (0.0137 + 0.05); // #1C2330

        return $contrastWithWhite >= $contrastWithDark ? '#FFFFFF' : '#1C2330';
    }
}
