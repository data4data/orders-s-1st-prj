<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Catalog\Port\ProductRepositoryInterface;
use App\Domain\Catalog\DocumentType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetContentPageHandler
{
    public function __construct(
        private ShippingInfo $shippingInfo,
        private ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetContentPage $query): array
    {
        return match ($query->page) {
            'shipping' => ['methods' => $this->shippingInfo->methods()],
            'safety-data-sheets' => ['documents' => $this->safetyDataSheets()],
            default => [],
        };
    }

    /**
     * @return list<array{product: string, slug: string, title: string, url: string, locale: string}>
     */
    private function safetyDataSheets(): array
    {
        $rows = [];
        foreach ($this->products->findActiveWithDocuments(DocumentType::SafetyDataSheet) as $product) {
            foreach ($product->getDocuments() as $document) {
                if (DocumentType::SafetyDataSheet === $document->getType()) {
                    $rows[] = ['product' => $product->getName(), 'slug' => $product->getSlug(), 'title' => $document->getTitle(), 'url' => $document->getUrl(), 'locale' => $document->getLocale()];
                }
            }
        }

        return $rows;
    }
}
