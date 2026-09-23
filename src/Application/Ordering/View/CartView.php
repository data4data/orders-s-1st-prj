<?php

declare(strict_types=1);

namespace App\Application\Ordering\View;

/**
 * The cart as the mini-cart drawer and the cart page show it. Amounts in cents.
 */
final readonly class CartView
{
    /**
     * @param list<array{variantId: string, sku: string, productName: string, variantName: string, slug: ?string, imageUrl: ?string, quantity: int, maxQuantity: int, unitGross: int, unitNet: int, lineGross: int, lineNet: int, stock: string}> $lines
     * @param array{code: string, error: ?string}|null                                                                                                                                                                                            $coupon
     * @param array{itemsNet: int, itemsGross: int, discountNet: int, discountGross: int, shippingGross: ?int, totalTax: int, totalGross: int}                                                                                                    $totals
     * @param array{code: string, name: string, gross: int}|null                                                                                                                                                                                  $shippingEstimate cheapest method to the store country
     */
    public function __construct(
        public ?string $id,
        public string $currency,
        public int $itemCount,
        public array $lines,
        public ?array $coupon,
        public array $totals,
        public ?array $shippingEstimate,
        public bool $canCheckout,
    ) {
    }
}
