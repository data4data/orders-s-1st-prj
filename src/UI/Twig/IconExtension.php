<?php

declare(strict_types=1);

namespace App\UI\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\Icons\IconRendererInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * {{ icon('success') }}: renders the Lucide icon behind a semantic name from
 * assets/shared/icons.json, the same map the Vue <AppIcon> and the jQuery helpers use.
 */
final class IconExtension
{
    /** @var array<string, string>|null */
    private ?array $map = null;

    public function __construct(
        private readonly IconRendererInterface $iconRenderer,
        #[Autowire('%kernel.project_dir%/assets/shared/icons.json')]
        private readonly string $mapFile,
    ) {
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    #[AsTwigFunction('icon', isSafe: ['html'])]
    public function icon(string $name, array $attributes = []): string
    {
        $lucide = $this->map()[$name] ?? throw new \InvalidArgumentException(sprintf('Unknown icon "%s". Add it to assets/shared/icons.json.', $name));

        if (isset($attributes['class'])) {
            $attributes['class'] = 'icon '.$attributes['class'];
        }

        return $this->iconRenderer->renderIcon('lucide:'.$lucide, $attributes);
    }

    /**
     * @return array<string, string>
     */
    private function map(): array
    {
        if (null === $this->map) {
            /** @var array<string, string> $decoded */
            $decoded = json_decode((string) file_get_contents($this->mapFile), true, 512, \JSON_THROW_ON_ERROR);
            unset($decoded['_comment']);
            $this->map = $decoded;
        }

        return $this->map;
    }
}
