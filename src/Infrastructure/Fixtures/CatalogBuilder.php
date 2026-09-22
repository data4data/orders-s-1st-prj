<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures;

use App\Domain\Catalog\AttributeType;
use App\Domain\Catalog\DocumentType;
use App\Entity\Attribute;
use App\Entity\Category;
use App\Entity\Country;
use App\Entity\Product;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductDocument;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Entity\Store;
use App\Entity\TaxCategory;
use App\Entity\TaxRate;
use App\Infrastructure\Tenancy\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds catalog data for demo stories and tests: VAT, attributes, categories and products with
 * pack sizes, specs, images and documents. Tenant data is written while the store is active.
 *
 * @phpstan-type CatalogData array{
 *     attributes: list<array{code: string, name: string, type: string, options?: list<string>, unit?: string, filterable?: bool}>,
 *     categories: list<array{slug: string, name: string, parent?: string}>,
 *     products: list<array<string, mixed>>,
 * }
 */
final readonly class CatalogBuilder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * Standard / reduced / zero VAT categories and the Dutch rates with their history (decision #10).
     *
     * @return array<string, TaxCategory>
     */
    public function dutchVat(Country $netherlands): array
    {
        $categories = [];
        foreach (['standard' => 'Standard rate', 'reduced' => 'Reduced rate', 'zero' => 'Zero rate'] as $code => $name) {
            $categories[$code] = $this->entityManager->getRepository(TaxCategory::class)->findOneBy(['code' => $code]) ?? new TaxCategory($code, $name);
            $this->entityManager->persist($categories[$code]);
        }
        foreach ([
            ['standard', '21.00', '2012-10-01', null],
            ['reduced', '6.00', '2001-01-01', '2018-12-31'],
            ['reduced', '9.00', '2019-01-01', null],
            ['zero', '0.00', '2001-01-01', null],
        ] as [$category, $rate, $from, $to]) {
            $this->entityManager->persist(new TaxRate($netherlands, $categories[$category], $rate, new \DateTimeImmutable($from), null !== $to ? new \DateTimeImmutable($to) : null));
        }
        $this->entityManager->flush();

        return $categories;
    }

    /**
     * @param CatalogData $catalog
     */
    public function build(Store $store, TaxCategory $defaultTaxCategory, array $catalog): void
    {
        $this->tenantContext->runAsStore($store, function () use ($store, $defaultTaxCategory, $catalog): void {
            $attributes = [];
            foreach ($catalog['attributes'] as $position => $data) {
                $attributes[$data['code']] = $this->attribute($data, $position);
            }
            $categories = [];
            foreach ($catalog['categories'] as $position => $data) {
                $categories[$data['slug']] = $this->category($data['slug'], $data['name'], isset($data['parent']) ? $categories[$data['parent']] : null, $position);
            }
            foreach ($catalog['products'] as $data) {
                $this->product($store, $defaultTaxCategory, $data, $attributes, $categories);
            }
            $this->entityManager->flush();
        });
    }

    /**
     * @param array{code: string, name: string, type: string, options?: list<string>, unit?: string, filterable?: bool} $data
     */
    private function attribute(array $data, int $position): Attribute
    {
        $attribute = new Attribute($data['code'], $data['name'], AttributeType::from($data['type']));
        $attribute->update($data['code'], $data['name'], AttributeType::from($data['type']), $data['unit'] ?? null, $data['filterable'] ?? true, $position);
        $attribute->replaceOptions($data['options'] ?? []);
        $this->entityManager->persist($attribute);

        return $attribute;
    }

    private function category(string $slug, string $name, ?Category $parent, int $position): Category
    {
        $category = new Category($slug, $name);
        $category->update($slug, $name, null, $position, true);
        $category->moveTo($parent);
        $this->entityManager->persist($category);

        return $category;
    }

    /**
     * @param array<string, mixed>     $data
     * @param array<string, Attribute> $attributes
     * @param array<string, Category>  $categories
     */
    private function product(Store $store, TaxCategory $taxCategory, array $data, array $attributes, array $categories): void
    {
        /** @var string $name */
        $name = $data['name'];
        /** @var string $slug */
        $slug = $data['slug'];
        $product = new Product($slug, $name, $taxCategory);
        $product->updateGeneral($slug, $name, "MyOil's", \is_string($data['description'] ?? null) ? $data['description'] : null, $taxCategory, (bool) ($data['active'] ?? true));
        /** @var list<string> $categorySlugs */
        $categorySlugs = $data['categories'];
        $product->replaceCategories(array_map(static fn (string $s) => $categories[$s], $categorySlugs));

        /** @var list<array{string, string, int, int, int, int}> $variants */
        $variants = $data['variants'];
        foreach ($variants as [$sku, $packName, $volumeMl, $weightG, $priceNet, $onHand]) {
            $variant = new ProductVariant($product, $sku, $packName, $volumeMl, $weightG, $priceNet);
            $variant->setOnHand($onHand);
            $product->addVariant($variant);
        }

        $values = [];
        /** @var array<string, list<string>|string|float> $specs */
        $specs = $data['specs'] ?? [];
        foreach ($specs as $code => $value) {
            $attribute = $attributes[$code];
            if ($attribute->getType()->usesOptions()) {
                foreach ((array) $value as $optionValue) {
                    $values[] = new ProductAttributeValue($product, $attribute, $attribute->findOption((string) $optionValue));
                }
            } elseif (AttributeType::Number === $attribute->getType()) {
                $values[] = new ProductAttributeValue($product, $attribute, valueNumber: is_array($value) ? '' : (string) $value);
            } else {
                $values[] = new ProductAttributeValue($product, $attribute, valueText: is_array($value) ? implode(', ', $value) : (string) $value);
            }
        }
        $product->replaceAttributeValues($values);

        [$background, $foreground] = [ltrim($store->getPrimaryColor(), '#'), ltrim($store->getAccentColor(), '#')];
        $product->replaceImages([new ProductImage($product, sprintf('https://placehold.co/600x600/%s/%s/png?text=%s', $background, $foreground, rawurlencode($name)), $name)]);

        $code = strtolower((string) $variants[0][0]);
        $product->replaceDocuments([
            new ProductDocument($product, DocumentType::SafetyDataSheet, 'Safety data sheet', sprintf('https://example.com/myoils/sds/%s-en.pdf', $code)),
            new ProductDocument($product, DocumentType::TechnicalDataSheet, 'Technical data sheet', sprintf('https://example.com/myoils/tds/%s-en.pdf', $code)),
        ]);

        $this->entityManager->persist($product);
    }
}
