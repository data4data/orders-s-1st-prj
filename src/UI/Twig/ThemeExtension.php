<?php

declare(strict_types=1);

namespace App\UI\Twig;

use App\Application\Bus\QueryBusInterface;
use App\Application\Storefront\GetStorefrontLayout;
use App\Application\Storefront\StorefrontLayoutView;
use App\UI\Theme\BrandPalette;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Layout helpers for the base templates: header/footer data, brand colours and the reference code.
 */
final class ThemeExtension implements ResetInterface
{
    private ?StorefrontLayoutView $layout = null;
    private bool $layoutLoaded = false;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsTwigFunction('storefront_layout')]
    public function storefrontLayout(): ?StorefrontLayoutView
    {
        if (!$this->layoutLoaded) {
            $this->layoutLoaded = true;
            try {
                $layout = $this->queryBus->ask(new GetStorefrontLayout());
                $this->layout = $layout instanceof StorefrontLayoutView ? $layout : null;
            } catch (\Throwable) {
                // Error pages must render even when the database is down: fall back to the neutral look.
                $this->layout = null;
            }
        }

        return $this->layout;
    }

    /**
     * <style> with the brand CSS variables; neutral platform colours without a store.
     */
    #[AsTwigFunction('brand_style', isSafe: ['html'])]
    public function brandStyle(?string $primary = null, ?string $accent = null): string
    {
        $css = '';
        foreach (BrandPalette::cssVariables($primary, $accent) as $name => $value) {
            $css .= $name.':'.$value.';';
        }

        return '<style id="brand-variables">:root{'.$css.'}</style>';
    }

    /** Reference code of the current request (X-Request-Id), also inside error sub-requests. */
    #[AsTwigFunction('request_reference')]
    public function requestReference(): ?string
    {
        $id = $this->requestStack->getMainRequest()?->attributes->get('_request_id');

        return \is_string($id) ? $id : null;
    }

    public function reset(): void
    {
        $this->layout = null;
        $this->layoutLoaded = false;
    }
}
