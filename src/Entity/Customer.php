<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Customer\AddressBookPolicy;
use App\Entity\Contract\TenantAwareInterface;
use App\Entity\Contract\TenantAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant table: a shopper account of one store (decision #6: the same email can register in
 * every shop). Always has at least one billing and one delivery address (AddressBookPolicy).
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer')]
#[ORM\UniqueConstraint(name: 'uniq_customer_store_email', columns: ['store_id', 'email'])]
class Customer implements TenantAwareInterface, UserInterface, PasswordAuthenticatedUserInterface
{
    use TenantAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\ManyToOne(targetEntity: CustomerAddress::class)]
    #[ORM\JoinColumn(name: 'default_billing_address_id', nullable: true, onDelete: 'SET NULL')]
    private ?CustomerAddress $defaultBillingAddress = null;

    #[ORM\ManyToOne(targetEntity: CustomerAddress::class)]
    #[ORM\JoinColumn(name: 'default_shipping_address_id', nullable: true, onDelete: 'SET NULL')]
    private ?CustomerAddress $defaultShippingAddress = null;

    /** @var Collection<int, CustomerAddress> */
    #[ORM\OneToMany(targetEntity: CustomerAddress::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $addresses;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $email,
        #[ORM\Column(length: 100)]
        private string $firstName,
        #[ORM\Column(length: 100)]
        private string $lastName,
    ) {
        $this->publicId = Uuid::v7();
        $this->email = self::normalizeEmail($email);
        $this->addresses = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : throw new \LogicException('Customer has no email.');
    }

    public function getRoles(): array
    {
        return ['ROLE_CUSTOMER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function updateProfile(string $firstName, string $lastName, ?string $phone): void
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, CustomerAddress> */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function findAddress(string $publicId): ?CustomerAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->getPublicId()->toRfc4122() === $publicId) {
                return $address;
            }
        }

        return null;
    }

    public function addAddress(CustomerAddress $address): void
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
        }
    }

    /**
     * Removes an address; the policy refuses to remove the last billing or delivery address.
     */
    public function removeAddress(CustomerAddress $address, AddressBookPolicy $policy): void
    {
        $policy->assertCanRemove($this->entries(), $address->getPublicId()->toRfc4122());
        $this->addresses->removeElement($address);
        $this->repairDefaults();
    }

    public function getDefaultBillingAddress(): ?CustomerAddress
    {
        return $this->defaultBillingAddress;
    }

    public function getDefaultShippingAddress(): ?CustomerAddress
    {
        return $this->defaultShippingAddress;
    }

    public function setDefaults(CustomerAddress $billing, CustomerAddress $shipping, AddressBookPolicy $policy): void
    {
        $policy->assertDefaults($this->entries(), $billing->getPublicId()->toRfc4122(), $shipping->getPublicId()->toRfc4122());
        $this->defaultBillingAddress = $billing;
        $this->defaultShippingAddress = $shipping;
    }

    /**
     * After a change, point the defaults at addresses that are still usable for their role.
     */
    public function repairDefaults(): void
    {
        if (null === $this->defaultBillingAddress || !$this->addresses->contains($this->defaultBillingAddress) || !$this->defaultBillingAddress->isUsableForBilling()) {
            $this->defaultBillingAddress = $this->addresses->findFirst(static fn ($k, CustomerAddress $a) => $a->isUsableForBilling());
        }
        if (null === $this->defaultShippingAddress || !$this->addresses->contains($this->defaultShippingAddress) || !$this->defaultShippingAddress->isUsableForShipping()) {
            $this->defaultShippingAddress = $this->addresses->findFirst(static fn ($k, CustomerAddress $a) => $a->isUsableForShipping());
        }
    }

    /**
     * @return list<\App\Domain\Customer\AddressBookEntry>
     */
    public function entries(): array
    {
        return array_values(array_map(static fn (CustomerAddress $a) => $a->toEntry(), $this->addresses->toArray()));
    }

    /**
     * Keeps the password hash out of the session; Symfony compares a CRC32C of it instead.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
