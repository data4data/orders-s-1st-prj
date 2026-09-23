<?php

declare(strict_types=1);

namespace App\Application\Ordering\Checkout;

use App\Application\Customer\Input\AddressInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The checkout wizard's final submit. Logged-in customers may pick address book entries by id;
 * guests type the addresses (snapshot only, decision #36).
 */
final class CheckoutInput
{
    #[Assert\Email(message: 'Enter a valid email address.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    public ?string $billingAddressId = null;

    #[Assert\When(expression: 'this.billingAddressId === null', constraints: [new Assert\NotNull(message: 'Enter the billing address.')])]
    #[Assert\Valid]
    public ?AddressInput $billing = null;

    public bool $shippingSameAsBilling = true;

    public ?string $shippingAddressId = null;

    #[Assert\When(expression: '!this.shippingSameAsBilling && this.shippingAddressId === null', constraints: [new Assert\NotNull(message: 'Enter the delivery address.')])]
    #[Assert\Valid]
    public ?AddressInput $shipping = null;

    #[Assert\NotBlank(message: 'Choose a shipping method.')]
    public string $shippingMethod = '';

    #[Assert\IsTrue(message: 'Please accept the terms and conditions.')]
    public bool $acceptTerms = false;

    /** The total the customer saw on the review step (cents); a different total stops the order. */
    public ?int $expectedTotal = null;
}
