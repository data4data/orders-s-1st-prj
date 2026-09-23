<?php

declare(strict_types=1);

namespace App\Application\Customer\View;

use App\Entity\CustomerAddress;

final readonly class AddressView
{
    public function __construct(
        public string $id,
        public ?string $label,
        public string $firstName,
        public string $lastName,
        public ?string $company,
        public ?string $vatId,
        public string $street,
        public string $houseNumber,
        public string $postcode,
        public string $city,
        public string $countryCode,
        public string $countryName,
        public ?string $phone,
        public bool $usableForBilling,
        public bool $usableForShipping,
        public bool $isDefaultBilling,
        public bool $isDefaultShipping,
    ) {
    }

    public static function from(CustomerAddress $address): self
    {
        $customer = $address->getCustomer();

        return new self(
            $address->getPublicId()->toRfc4122(), $address->getLabel(), $address->getFirstName(), $address->getLastName(),
            $address->getCompany(), $address->getVatId(), $address->getStreet(), $address->getHouseNumber(), $address->getPostcode(),
            $address->getCity(), $address->getCountry()->getCode(), $address->getCountry()->getName(), $address->getPhone(),
            $address->isUsableForBilling(), $address->isUsableForShipping(),
            $customer->getDefaultBillingAddress() === $address, $customer->getDefaultShippingAddress() === $address,
        );
    }
}
