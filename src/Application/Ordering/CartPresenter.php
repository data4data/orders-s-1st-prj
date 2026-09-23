<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Ordering\Port\ShippingMethodRepositoryInterface;
use App\Application\Ordering\View\CartView;
use App\Entity\Order;
use App\Entity\Store;

/**
 * Turns a draft order into the CartView, with live prices and stock.
 */
final readonly class CartPresenter
{
    public function __construct(
        private OrderPricer $pricer,
        private ShippingMethodRepositoryInterface $shippingMethods,
    ) {
    }

    public function present(Store $store, ?Order $cart): CartView
    {
        $currency = $store->getCurrencyCode();
        if (null === $cart || 0 === $cart->getItems()->count()) {
            return new CartView($cart?->getPublicId()->toRfc4122(), $currency, 0, [], null, ['itemsNet' => 0, 'itemsGross' => 0, 'discountNet' => 0, 'discountGross' => 0, 'shippingGross' => null, 'totalTax' => 0, 'totalGross' => 0], null, false);
        }

        $estimate = null;
        foreach ($this->pricer->shippingOptions($store, $cart, $this->shippingMethods->findActive()) as $option) {
            if (null !== $option['gross'] && (null === $estimate || $option['gross'] < $estimate['gross'])) {
                $estimate = ['code' => $option['method']->getCode(), 'name' => $option['method']->getName(), 'gross' => $option['gross']];
            }
        }
        $method = null !== $estimate ? $this->shippingMethods->findActiveByCode($estimate['code']) : null;
        $priced = $this->pricer->price($store, $cart, $method);

        $lines = [];
        $itemsGross = 0;
        $canCheckout = [] === $priced->unavailable;
        foreach ($cart->getItems() as $item) {
            $variant = $item->getVariant();
            $id = $variant?->getPublicId()->toRfc4122();
            $line = null !== $id ? ($priced->lines[$id] ?? null) : null;
            $available = $variant?->stock()->available() ?? 0;
            $stock = match (true) {
                null === $line => 'unavailable',
                $available < $item->getQuantity() => 'insufficient',
                $variant->stock()->isLow($store->getLowStockThreshold()) => 'low',
                default => 'ok',
            };
            if ('unavailable' === $stock || 'insufficient' === $stock) {
                $canCheckout = false;
            }
            $unitGross = null !== $line ? $line->taxRate->grossFor($line->unitNet)->amount : 0;
            $gross = null !== $line ? $line->taxRate->grossFor($line->netBeforeDiscount)->amount : 0;
            $itemsGross += $gross;
            $image = $variant?->getProduct()->getImages()->first() ?: null;
            $lines[] = [
                'variantId' => $id ?? $item->getSku(),
                'sku' => $item->getSku(),
                'productName' => $item->getProductName(),
                'variantName' => $item->getVariantName(),
                'slug' => $variant?->getProduct()->getSlug(),
                'imageUrl' => $image?->getUrl(),
                'quantity' => $item->getQuantity(),
                'maxQuantity' => max(1, min(Order::MAX_QUANTITY, $available)),
                'unitGross' => $unitGross,
                'unitNet' => $line?->unitNet->amount ?? 0,
                'lineGross' => $gross,
                'lineNet' => $line?->netBeforeDiscount->amount ?? 0,
                'stock' => $stock,
            ];
        }

        $totals = $priced->totals;
        $coupon = null !== $cart->getCouponCode() ? ['code' => $cart->getCouponCode(), 'error' => $priced->couponError?->value] : null;

        return new CartView(
            $cart->getPublicId()->toRfc4122(),
            $currency,
            $cart->itemCount(),
            $lines,
            $coupon,
            [
                'itemsNet' => $totals->itemsNet->amount,
                'itemsGross' => $itemsGross,
                'discountNet' => $totals->discountNet->amount,
                'discountGross' => $itemsGross - $priced->itemsGrossAfterDiscount,
                'shippingGross' => $priced->shipping?->gross->amount,
                'totalTax' => $totals->totalTax->amount,
                'totalGross' => $totals->totalGross->amount,
            ],
            $estimate,
            $canCheckout,
        );
    }
}
