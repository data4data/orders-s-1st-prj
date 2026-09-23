<?php

declare(strict_types=1);

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;

/**
 * Address snapshot on an order (billing_* and shipping_* columns). Copied at checkout, never
 * changed afterwards. The columns are nullable because a draft order (the cart) has no address yet.
 */
#[ORM\Embeddable]
final class PostalAddress
{
    public function __construct(
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $firstName = null,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $lastName = null,
        #[ORM\Column(length: 150, nullable: true)]
        private ?string $company = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $vatId = null,
        #[ORM\Column(length: 150, nullable: true)]
        private ?string $street = null,
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $houseNumber = null,
        #[ORM\Column(length: 16, nullable: true)]
        private ?string $postcode = null,
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $city = null,
        #[ORM\Column(length: 2, nullable: true, options: ['fixed' => true])]
        private ?string $countryCode = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $phone = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->street;
    }

    /**
     * @return array{firstName: ?string, lastName: ?string, company: ?string, vatId: ?string, street: ?string, houseNumber: ?string, postcode: ?string, city: ?string, countryCode: ?string, phone: ?string}
     */
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'company' => $this->company,
            'vatId' => $this->vatId,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'countryCode' => $this->countryCode,
            'phone' => $this->phone,
        ];
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function fullName(): string
    {
        return trim(($this->firstName ?? '').' '.($this->lastName ?? ''));
    }
}
