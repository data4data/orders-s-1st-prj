<?php

declare(strict_types=1);

namespace App\Application\Customer\Input;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Registration form: account details plus the billing address, and a delivery address unless
 * "delivery same as billing" stays ticked (decision #36).
 */
final class RegisterCustomerInput
{
    #[Assert\NotBlank(message: 'Enter your email address.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Choose a password.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Use at least 8 characters.')]
    public string $password = '';

    #[Assert\NotBlank(message: 'Enter your first name.')]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Enter your last name.')]
    #[Assert\Length(max: 100)]
    public string $lastName = '';

    #[Assert\Length(max: 32)]
    public ?string $phone = null;

    #[Assert\Valid]
    public AddressInput $billing;

    public bool $deliverySameAsBilling = true;

    /** Validated by validateDelivery() only when it is used. */
    public AddressInput $delivery;

    #[Assert\IsTrue(message: 'Please accept the terms and conditions.')]
    public bool $acceptTerms = false;

    public function __construct()
    {
        $this->billing = new AddressInput();
        $this->delivery = new AddressInput();
    }

    #[Assert\Callback]
    public function validateDelivery(ExecutionContextInterface $context): void
    {
        if (!$this->deliverySameAsBilling) {
            $context->getValidator()->inContext($context)->atPath('delivery')->validate($this->delivery);
        }
    }
}
