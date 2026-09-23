<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Customer\AddressBookEntry;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use App\Entity\Embeddable\PostalAddress;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant table: an address in a customer's address book. One row can serve both roles
 * (usable_for_billing + usable_for_shipping), decision #35.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_address')]
class CustomerAddress implements TenantAwareInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    private string $lastName = '';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $vatId = null;

    #[ORM\Column(length: 150)]
    private string $street = '';

    #[ORM\Column(length: 20)]
    private string $houseNumber = '';

    #[ORM\Column(length: 16)]
    private string $postcode = '';

    #[ORM\Column(length: 100)]
    private string $city = '';

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'code', nullable: false)]
    private Country $country;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private bool $usableForBilling = true;

    #[ORM\Column]
    private bool $usableForShipping = true;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'addresses')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Customer $customer,
        Country $country,
    ) {
        $this->publicId = Uuid::v7();
        $this->country = $country;
    }

    public function update(
        ?string $label, string $firstName, string $lastName, ?string $company, ?string $vatId, string $street,
        string $houseNumber, string $postcode, string $city, Country $country, ?string $phone,
        bool $usableForBilling, bool $usableForShipping,
    ): void {
        $this->label = $label;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->company = $company;
        $this->vatId = $vatId;
        $this->street = $street;
        $this->houseNumber = $houseNumber;
        $this->postcode = strtoupper($postcode);
        $this->city = $city;
        $this->country = $country;
        $this->phone = $phone;
        $this->usableForBilling = $usableForBilling;
        $this->usableForShipping = $usableForShipping;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isUsableForBilling(): bool
    {
        return $this->usableForBilling;
    }

    public function isUsableForShipping(): bool
    {
        return $this->usableForShipping;
    }

    public function toEntry(): AddressBookEntry
    {
        return new AddressBookEntry($this->publicId->toRfc4122(), $this->usableForBilling, $this->usableForShipping);
    }

    public function snapshot(): PostalAddress
    {
        return new PostalAddress($this->firstName, $this->lastName, $this->company, $this->vatId, $this->street, $this->houseNumber, $this->postcode, $this->city, $this->country->getCode(), $this->phone);
    }
}
