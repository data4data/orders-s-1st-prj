<?php

declare(strict_types=1);

namespace App\Application\Tenancy\View;

use App\Entity\Store;

/**
 * Public store information: name, locale and branding for the storefront header and theme.
 */
final readonly class StoreView
{
    public function __construct(
        public string $publicId,
        public string $code,
        public string $name,
        public string $countryCode,
        public string $currencyCode,
        public string $locale,
        public ?string $logoUrl,
        public ?string $faviconUrl,
        public string $primaryColor,
        public string $accentColor,
        public ?string $contactEmail = null,
    ) {
    }

    public static function fromStore(Store $store): self
    {
        return new self(
            $store->getPublicId()->toRfc4122(),
            $store->getCode(),
            $store->getName(),
            $store->getCountry()->getCode(),
            $store->getCurrencyCode(),
            $store->getDefaultLocale(),
            $store->getLogoUrl(),
            $store->getFaviconUrl(),
            $store->getPrimaryColor(),
            $store->getAccentColor(),
            $store->getContactEmail(),
        );
    }
}
