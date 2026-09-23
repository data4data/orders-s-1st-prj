<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

use App\Application\Customer\View\AddressView;
use App\Application\Ordering\View\CartView;

final readonly class CheckoutView
{
    /**
     * @param array{email: string, firstName: string, lastName: string, addresses: list<AddressView>}|null                          $customer
     * @param list<array{code: string, name: string}>                                                                               $countries
     * @param list<array{code: string, name: string, description: ?string, net: ?int, gross: ?int, totalGross: int, totalTax: int}> $shippingOptions for the chosen country
     */
    public function __construct(
        public CartView $cart,
        public ?array $customer,
        public array $countries,
        public string $country,
        public array $shippingOptions,
    ) {
    }
}
