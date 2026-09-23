<?php

declare(strict_types=1);

namespace App\Application\Ordering\View;

use App\Entity\Order;
use App\Entity\Payment;

/**
 * A placed order as the customer sees it (account, confirmation page). Amounts in cents.
 */
final readonly class OrderView
{
    /**
     * @param list<array{sku: string, productName: string, variantName: string, quantity: int, unitNet: int, taxRate: ?string, lineNet: int, lineTax: int, lineGross: int}> $items
     * @param array<string, ?string>                                                                                                                                        $billingAddress
     * @param array<string, ?string>                                                                                                                                        $shippingAddress
     * @param array{itemsNet: int, discountNet: int, shippingNet: int, totalNet: int, totalTax: int, totalGross: int}                                                       $totals
     */
    public function __construct(
        public string $id,
        public ?string $number,
        public string $state,
        public string $stateLabel,
        public ?string $placedAt,
        public string $currency,
        public ?string $email,
        public int $itemCount,
        public array $items,
        public array $billingAddress,
        public array $shippingAddress,
        public ?string $shippingMethod,
        public ?string $couponCode,
        public array $totals,
        public ?string $paymentState,
        public ?string $paymentUrl,
    ) {
    }

    public static function from(Order $order, ?Payment $payment = null): self
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'productName' => $item->getProductName(),
                'variantName' => $item->getVariantName(),
                'quantity' => $item->getQuantity(),
                'unitNet' => $item->getUnitPriceNet(),
                'taxRate' => $item->getTaxRate(),
                'lineNet' => $item->getLineNet(),
                'lineTax' => $item->getLineTax(),
                'lineGross' => $item->getLineGross(),
            ];
        }

        return new self(
            $order->getPublicId()->toRfc4122(),
            $order->getOrderNumber(),
            $order->getState(),
            $order->state()->label(),
            $order->getPlacedAt()?->format(\DATE_ATOM),
            $order->getCurrencyCode(),
            $order->getCustomerEmail(),
            $order->itemCount(),
            $items,
            $order->getBillingAddress()->toArray(),
            $order->getShippingAddress()->toArray(),
            $order->getShippingMethodName(),
            $order->getCouponCode(),
            [
                'itemsNet' => $order->getItemsNet(),
                'discountNet' => $order->getDiscountNet(),
                'shippingNet' => $order->getShippingNet(),
                'totalNet' => $order->getTotalNet(),
                'totalTax' => $order->getTotalTax(),
                'totalGross' => $order->getTotalGross(),
            ],
            $payment?->getState(),
            // The customer can (re)open the payment page while the payment is still open.
            null !== $payment && $payment->state()->isOpen() ? $payment->getCheckoutUrl() : null,
        );
    }
}
