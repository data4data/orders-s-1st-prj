<?php

declare(strict_types=1);

namespace App\Application\Ordering;

use App\Application\Catalog\CatalogPricing;
use App\Domain\Discount\CouponNotApplicableException;
use App\Domain\Discount\DiscountableLine;
use App\Domain\Discount\DiscountCalculator;
use App\Domain\Discount\DiscountContext;
use App\Domain\Discount\DiscountResult;
use App\Domain\Pricing\LinePricer;
use App\Domain\Pricing\OrderTotals;
use App\Domain\Pricing\PriceLine;
use App\Domain\Shared\Quantity;
use App\Domain\Shipping\Shipment;
use App\Domain\Shipping\ShippingNotAvailableException;
use App\Domain\Shipping\ShippingQuoter;
use App\Entity\Order;
use App\Entity\ShippingMethod;
use App\Entity\Store;
use Psr\Clock\ClockInterface;

/**
 * Prices a cart or an order being placed with today's prices, VAT, coupon and shipping. The cart
 * page and PlaceOrder use the same code, so the customer pays exactly what the cart showed.
 */
final readonly class OrderPricer
{
    public function __construct(
        private CatalogPricing $catalogPricing,
        private LinePricer $linePricer,
        private DiscountCalculator $discounts,
        private ShippingQuoter $shippingQuoter,
        private ClockInterface $clock,
    ) {
    }

    public function price(Store $store, Order $order, ?ShippingMethod $method = null, ?string $destinationCountry = null): PricedOrder
    {
        $currency = $order->getCurrencyCode();
        $lines = [];
        $unavailable = [];
        $weight = 0;
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            if (null === $variant || !$variant->isActive() || !$variant->getProduct()->isActive()) {
                $unavailable[] = $variant?->getPublicId()->toRfc4122() ?? $item->getSku();
                continue;
            }
            $id = $variant->getPublicId()->toRfc4122();
            $rate = $this->catalogPricing->rateFor($store, $variant->getProduct()->getTaxCategory());
            $lines[$id] = new PriceLine($id, $variant->priceNet($currency), Quantity::of($item->getQuantity()), $rate);
            $weight += $variant->getWeightG() * $item->getQuantity();
        }

        [$discount, $couponError] = $this->discount($order, $lines);
        $priced = [];
        foreach ($lines as $id => $line) {
            $priced[$id] = $this->linePricer->price($line, $discount->forLine($id));
        }
        $itemsGross = OrderTotals::calculate($currency, array_values($priced))->totalGross;

        $shipping = null;
        $shippingError = null;
        if (null !== $method) {
            try {
                $quote = $this->shippingQuoter->quote($method->toSpec(), new Shipment($itemsGross, $weight, $destinationCountry ?? $store->getCountry()->getCode()));
                $rate = $this->catalogPricing->rateFor($store, $method->getTaxCategory());
                $shipping = $this->linePricer->price(new PriceLine('shipping', $quote, Quantity::of(1), $rate));
            } catch (ShippingNotAvailableException $exception) {
                $shippingError = $exception->getMessage();
            }
        }

        return new PricedOrder(
            $priced,
            OrderTotals::calculate($currency, array_values($priced), $shipping),
            $shipping,
            $couponError,
            $shippingError,
            $unavailable,
            $weight,
            $itemsGross->amount,
        );
    }

    /**
     * @param array<string, PriceLine> $lines
     *
     * @return array{DiscountResult, ?\App\Domain\Discount\CouponRejectionReason}
     */
    private function discount(Order $order, array $lines): array
    {
        $currency = $order->getCurrencyCode();
        $coupon = $order->getCoupon();
        if (null === $coupon) {
            return [DiscountResult::none($currency), null];
        }

        $context = new DiscountContext(
            $currency,
            array_values(array_map(static fn (PriceLine $l) => new DiscountableLine($l->lineId, $l->netBeforeDiscount()), $lines)),
            $coupon->toDomain($currency),
            $this->clock->now(),
        );
        try {
            return [$this->discounts->calculate($context), null];
        } catch (CouponNotApplicableException $exception) {
            return [DiscountResult::none($currency), $exception->reason];
        }
    }

    /**
     * Every active shipping method with its price for this cart (null = not available).
     *
     * @param list<ShippingMethod> $methods
     *
     * @return list<array{method: ShippingMethod, net: ?int, gross: ?int, totalGross: int, totalTax: int}>
     */
    public function shippingOptions(Store $store, Order $order, array $methods, ?string $destinationCountry = null): array
    {
        $options = [];
        foreach ($methods as $method) {
            $priced = $this->price($store, $order, $method, $destinationCountry);
            $options[] = [
                'method' => $method,
                'net' => $priced->shipping?->net->amount,
                'gross' => $priced->shipping?->gross->amount,
                'totalGross' => $priced->totals->totalGross->amount,
                'totalTax' => $priced->totals->totalTax->amount,
            ];
        }

        return $options;
    }
}
