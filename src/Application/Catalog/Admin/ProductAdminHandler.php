<?php

declare(strict_types=1);

namespace App\Application\Catalog\Admin;

use App\Application\Catalog\CatalogPricing;
use App\Application\Catalog\Input\ProductInput;
use App\Application\Catalog\Port\AttributeRepositoryInterface;
use App\Application\Catalog\Port\CategoryRepositoryInterface;
use App\Application\Catalog\Port\ProductRepositoryInterface;
use App\Application\Catalog\Port\TaxCategoryRepositoryInterface;
use App\Application\Exception\NotFoundException;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Catalog\AttributeType;
use App\Domain\Catalog\DocumentType;
use App\Domain\Money\Money;
use App\Entity\Product;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductDocument;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Admin: products, saved as one unit (general, pack sizes & stock, specs, images, documents).
 */
final readonly class ProductAdminHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private AttributeRepositoryInterface $attributes,
        private TaxCategoryRepositoryInterface $taxCategories,
        private CatalogPricing $pricing,
    ) {
    }

    /**
     * @return list<array{id: int, code: string, name: string, rate: string}>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function taxCategories(ListTaxCategories $query): array
    {
        $store = $this->tenantContext->requireStore();

        return array_map(fn ($category) => [
            'id' => (int) $category->getId(),
            'code' => $category->getCode(),
            'name' => $category->getName(),
            'rate' => $this->pricing->rateFor($store, $category)->toString(),
        ], $this->taxCategories->findAll());
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListAdminProducts $query): array
    {
        $this->tenantContext->requireStore();
        $page = $this->products->adminPage(trim($query->search), max(1, $query->page), min(100, max(1, $query->perPage)));

        return ['total' => $page['total'], 'items' => array_map(function (Product $product) {
            $store = $product->getStore() ?? throw new \LogicException('Product without store.');
            $gross = array_map(fn ($v) => $this->pricing->price($store, $v)->gross, $product->getVariants()->toArray());
            $low = array_filter($product->getVariants()->toArray(), static fn ($v) => $v->isActive() && $v->stock()->isLow($store->getLowStockThreshold()));

            return [
                'publicId' => $product->getPublicId()->toRfc4122(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'isActive' => $product->isActive(),
                'packs' => $product->getVariants()->count(),
                'fromGross' => [] === $gross ? null : min($gross),
                'toGross' => [] === $gross ? null : max($gross),
                'currency' => $store->getCurrencyCode(),
                'stock' => array_sum(array_map(static fn ($v) => $v->stock()->available(), $product->getVariants()->toArray())),
                'lowStock' => [] !== $low,
            ];
        }, $page['items'])];
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetAdminProduct $query): array
    {
        $store = $this->tenantContext->requireStore();
        $product = $this->find($query->publicId);

        $specs = [];
        foreach ($product->getAttributeValues() as $value) {
            $id = (int) $value->getAttribute()->getId();
            $specs[$id] ??= ['attributeId' => $id, 'options' => [], 'value' => null];
            if (null !== $value->getOption()) {
                $specs[$id]['options'][] = $value->getOption()->getValue();
            } else {
                $specs[$id]['value'] = $value->getValueNumber() ?? $value->getValueText();
            }
        }

        return [
            'publicId' => $product->getPublicId()->toRfc4122(),
            'version' => $product->getVersion(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'brand' => $product->getBrand(),
            'description' => $product->getDescription(),
            'taxCategoryId' => $product->getTaxCategory()->getId(),
            'categoryIds' => array_values(array_map(static fn ($c) => $c->getId(), $product->getCategories()->toArray())),
            'isActive' => $product->isActive(),
            'vatRate' => $this->pricing->rateFor($store, $product->getTaxCategory())->toString(),
            'currency' => $store->getCurrencyCode(),
            'variants' => array_values(array_map(fn (ProductVariant $v) => [
                'publicId' => $v->getPublicId()->toRfc4122(),
                'sku' => $v->getSku(),
                'name' => $v->getName(),
                'volumeMl' => $v->getVolumeMl(),
                'weightG' => $v->getWeightG(),
                'priceNet' => Money::of($v->getPriceNet(), $store->getCurrencyCode())->toDecimalString(),
                'onHand' => $v->getOnHand(),
                'reserved' => $v->getReserved(),
                'isActive' => $v->isActive(),
            ], $product->getVariants()->toArray())),
            'specs' => array_values($specs),
            'images' => array_values(array_map(static fn (ProductImage $i) => ['url' => $i->getUrl(), 'alt' => $i->getAltText(), 'variantSku' => $i->getVariant()?->getSku()], $product->getImages()->toArray())),
            'documents' => array_values(array_map(static fn (ProductDocument $d) => ['type' => $d->getType()->value, 'title' => $d->getTitle(), 'url' => $d->getUrl(), 'locale' => $d->getLocale()], $product->getDocuments()->toArray())),
        ];
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveProduct $command): string
    {
        $store = $this->tenantContext->requireStore();
        $input = $command->input;
        $product = null !== $command->publicId ? $this->find($command->publicId) : null;

        if (null !== $product && null !== $input->version && $input->version !== $product->getVersion()) {
            throw OptimisticLockException::lockFailedVersionMismatch($product, $input->version, $product->getVersion());
        }

        $this->validateUniqueness($input, $product);
        $taxCategory = $this->taxCategories->findById($input->taxCategoryId) ?? throw ValidationException::forField('taxCategoryId', 'Choose a VAT category.');
        $categories = [];
        foreach ($input->categoryIds as $index => $categoryId) {
            $categories[] = $this->categories->findById($categoryId) ?? throw ValidationException::forField("categoryIds[$index]", 'This category no longer exists.');
        }

        $product ??= new Product($input->slug, $input->name, $taxCategory);
        $product->updateGeneral($input->slug, $input->name, $input->brand, '' === $input->description ? null : $input->description, $taxCategory, $input->isActive);
        $product->replaceCategories($categories);
        $this->applyVariants($product, $input, $store->getCurrencyCode());
        $this->applySpecs($product, $input);
        $this->applyImages($product, $input);
        $product->replaceDocuments(array_map(
            static fn ($d) => new ProductDocument($product, DocumentType::from($d->type), $d->title, $d->url, $d->locale),
            $input->documents,
        ));

        $this->products->save($product);

        return $product->getPublicId()->toRfc4122();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteProduct $command): void
    {
        $this->tenantContext->requireStore();
        $product = $this->find($command->publicId);
        foreach ($product->getVariants() as $variant) {
            if ($variant->getReserved() > 0) {
                throw new \DomainException(sprintf('Pack %s has %d unit(s) reserved by open orders; it cannot be deleted now.', $variant->getSku(), $variant->getReserved()));
            }
        }
        $this->products->remove($product);
    }

    private function find(string $publicId): Product
    {
        $product = Uuid::isValid($publicId) ? $this->products->findByPublicId(Uuid::fromString($publicId)) : null;

        return $product ?? throw NotFoundException::of('Product', $publicId);
    }

    private function validateUniqueness(ProductInput $input, ?Product $product): void
    {
        $violations = [];
        if ($this->products->slugExists($input->slug, $product?->getId())) {
            $violations[] = ['propertyPath' => 'slug', 'message' => 'Another product already uses this URL name.'];
        }
        $skus = array_map(static fn ($v) => $v->sku, $input->variants);
        foreach (array_count_values($skus) as $sku => $count) {
            if ($count > 1) {
                foreach ($input->variants as $index => $variant) {
                    if ($variant->sku === (string) $sku) {
                        $violations[] = ['propertyPath' => "variants[$index].sku", 'message' => 'Each pack needs its own SKU.'];
                    }
                }
            }
        }
        $taken = $this->products->skusUsedElsewhere(array_values(array_unique($skus)), $product?->getId());
        foreach ($input->variants as $index => $variant) {
            if (\in_array($variant->sku, $taken, true)) {
                $violations[] = ['propertyPath' => "variants[$index].sku", 'message' => 'Another product already uses this SKU.'];
            }
        }
        if ([] !== $violations) {
            throw new ValidationException($violations);
        }
    }

    private function applyVariants(Product $product, ProductInput $input, string $currency): void
    {
        $kept = [];
        foreach ($input->variants as $index => $data) {
            $variant = null !== $data->publicId ? $product->findVariant($data->publicId) : null;
            $priceNet = Money::fromDecimal($data->priceNet, $currency)->amount;
            if (null === $variant) {
                $variant = new ProductVariant($product, $data->sku, $data->name, $data->volumeMl, $data->weightG, $priceNet);
                $product->addVariant($variant);
            }
            $variant->update($data->sku, $data->name, $data->volumeMl, $data->weightG, $priceNet, $data->isActive);
            try {
                $variant->setOnHand($data->onHand);
            } catch (\InvalidArgumentException) {
                throw ValidationException::forField("variants[$index].onHand", sprintf('Stock cannot be lower than the %d unit(s) reserved by open orders.', $variant->getReserved()));
            }
            $kept[] = $variant;
        }
        foreach ($product->getVariants()->toArray() as $variant) {
            if (!\in_array($variant, $kept, true)) {
                if ($variant->getReserved() > 0) {
                    throw ValidationException::forField('variants', sprintf('Pack %s has units reserved by open orders and cannot be removed.', $variant->getSku()));
                }
                $product->removeVariant($variant);
            }
        }
    }

    private function applySpecs(Product $product, ProductInput $input): void
    {
        $attributes = [];
        foreach ($this->attributes->findAllOrdered() as $attribute) {
            $attributes[(int) $attribute->getId()] = $attribute;
        }

        $values = [];
        foreach ($input->specs as $index => $spec) {
            $attribute = $attributes[$spec->attributeId] ?? throw ValidationException::forField("specs[$index].attributeId", 'This attribute no longer exists.');
            if ($attribute->getType()->usesOptions()) {
                if (AttributeType::Select === $attribute->getType() && \count($spec->options) > 1) {
                    throw ValidationException::forField("specs[$index].options", sprintf('%s takes a single value.', $attribute->getName()));
                }
                foreach ($spec->options as $optionValue) {
                    $option = $attribute->findOption($optionValue) ?? throw ValidationException::forField("specs[$index].options", sprintf('"%s" is not an option of %s.', $optionValue, $attribute->getName()));
                    $values[] = new ProductAttributeValue($product, $attribute, $option);
                }
            } elseif (null !== $spec->value && '' !== trim($spec->value)) {
                if (AttributeType::Number === $attribute->getType()) {
                    if (!is_numeric($spec->value)) {
                        throw ValidationException::forField("specs[$index].value", sprintf('%s must be a number.', $attribute->getName()));
                    }
                    $values[] = new ProductAttributeValue($product, $attribute, valueNumber: (string) $spec->value);
                } else {
                    $values[] = new ProductAttributeValue($product, $attribute, valueText: trim($spec->value));
                }
            }
        }
        $product->replaceAttributeValues($values);
    }

    private function applyImages(Product $product, ProductInput $input): void
    {
        $images = [];
        foreach ($input->images as $position => $data) {
            $variant = null;
            if (null !== $data->variantSku && '' !== $data->variantSku) {
                foreach ($product->getVariants() as $candidate) {
                    if ($candidate->getSku() === $data->variantSku) {
                        $variant = $candidate;
                    }
                }
                if (null === $variant) {
                    throw ValidationException::forField("images[$position].variantSku", 'Choose one of the pack sizes above.');
                }
            }
            $images[] = new ProductImage($product, $data->url, $data->alt, $position, $variant);
        }
        $product->replaceImages($images);
    }
}
