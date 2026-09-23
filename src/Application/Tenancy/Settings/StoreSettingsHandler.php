<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

use App\Application\Exception\NotFoundException;
use App\Application\Payment\PaymentGatewayRegistry;
use App\Application\Tenancy\TenantContextInterface;
use App\Application\Validation\ValidationException;
use App\Domain\Money\Money;
use App\Domain\Tenancy\StoreRole;
use App\Entity\Country;
use App\Entity\ShippingMethod;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Entity\StoreDomain;
use App\Entity\StoreMembership;
use App\Entity\TaxCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Settings of the selected store (managers and owners; the controller checks ROLE_STORE_MANAGER).
 * Plain CRUD on configuration, so it works with Doctrine directly.
 */
final readonly class StoreSettingsHandler
{
    public function __construct(
        private TenantContextInterface $tenantContext,
        private EntityManagerInterface $entityManager,
        private PaymentGatewayRegistry $gateways,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function settings(GetStoreSettings $query): array
    {
        $store = $this->store();

        return [
            'profile' => [
                'name' => $store->getName(),
                'contactEmail' => $store->getContactEmail(),
                'logoUrl' => $store->getLogoUrl(),
                'faviconUrl' => $store->getFaviconUrl(),
                'primaryColor' => $store->getPrimaryColor(),
                'accentColor' => $store->getAccentColor(),
                'orderNumberPrefix' => $store->getOrderNumberPrefix(),
                'lowStockThreshold' => $store->getLowStockThreshold(),
            ],
            'domains' => array_map(static fn (StoreDomain $d) => ['id' => $d->getId(), 'host' => $d->getHost(), 'isPrimary' => $d->isPrimary()], $this->domains($store)),
            'shippingMethods' => array_map(fn (ShippingMethod $m) => $this->shippingRow($m), $this->entityManager->getRepository(ShippingMethod::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC'])),
            'countries' => array_map(static fn (Country $c) => ['code' => $c->getCode(), 'name' => $c->getName()], $this->entityManager->getRepository(Country::class)->findBy([], ['name' => 'ASC'])),
            'gateway' => ['current' => $store->getPaymentGatewayCode(), 'available' => $this->gateways->codes()],
            'staff' => array_map(static fn (StoreMembership $m) => [
                'id' => $m->getId(),
                'email' => $m->getStaffUser()->getEmail(),
                'name' => trim($m->getStaffUser()->getFirstName().' '.$m->getStaffUser()->getLastName()),
                'role' => $m->getRole()->value,
            ], $this->memberships($store)),
            'canGrantOwner' => $this->authorization->isGranted('ROLE_STORE_OWNER') || $this->authorization->isGranted('ROLE_SUPER_ADMIN'),
            'notifications' => $store->getNotifications(),
        ];
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function updateProfile(UpdateStoreProfile $command): void
    {
        $input = $command->input;
        $store = $this->store();
        $store->rename(trim($input->name));
        $store->changeContact(self::blank($input->contactEmail), $store->getDefaultLocale());
        $store->changeBranding(self::blank($input->logoUrl), self::blank($input->faviconUrl), $input->primaryColor, $input->accentColor);
        $store->changeOrderNumberPrefix($input->orderNumberPrefix);
        $store->changeLowStockThreshold($input->lowStockThreshold);
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function addDomain(AddStoreDomain $command): void
    {
        $host = strtolower(trim($command->host));
        if (1 !== preg_match('/^(?=.{3,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            throw ValidationException::forField('host', 'Enter a host name like shop.example.com.');
        }
        if (null !== $this->entityManager->getRepository(StoreDomain::class)->findOneBy(['host' => $host])) {
            throw ValidationException::forField('host', 'This host name is already used by a shop.');
        }
        $store = $this->store();
        $this->entityManager->persist(new StoreDomain($store, $host, [] === $this->domains($store)));
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function removeDomain(RemoveStoreDomain $command): void
    {
        $domain = $this->domain($command->id);
        if ($domain->isPrimary()) {
            throw new \DomainException('The primary address cannot be removed. Make another address primary first.');
        }
        $this->entityManager->remove($domain);
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function makePrimary(MakeDomainPrimary $command): void
    {
        $primary = $this->domain($command->id);
        foreach ($this->domains($this->store()) as $domain) {
            $domain->markPrimary($domain === $primary);
        }
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function saveShippingMethod(SaveShippingMethod $command): int
    {
        $input = $command->input;
        $repository = $this->entityManager->getRepository(ShippingMethod::class);
        $same = $repository->findOneBy(['code' => $input->code]);
        if (null !== $same && $same->getId() !== $command->id) {
            throw ValidationException::forField('code', 'Another shipping method already uses this code.');
        }

        $currency = $this->store()->getCurrencyCode();
        $cents = static fn (?string $v) => null !== $v && '' !== $v ? Money::fromDecimal($v, $currency)->amount : 0;
        $config = match ($input->calculator) {
            'flat' => ['amount' => $cents($input->amount)],
            'free_over_threshold' => ['amount' => $cents($input->amount), 'threshold' => $cents($input->threshold)],
            default => ['brackets' => array_map(static fn (array $b) => [
                'up_to_grams' => null !== $b['upToKg'] && '' !== $b['upToKg'] ? (int) round((float) $b['upToKg'] * 1000) : null,
                'amount' => $cents($b['amount']),
            ], $input->brackets)],
        };
        $countries = [] === $input->allowedCountries ? null : array_map('strtoupper', $input->allowedCountries);

        if (null === $command->id) {
            $standard = $this->entityManager->getRepository(TaxCategory::class)->findOneBy(['code' => 'standard'])
                ?? throw new \LogicException('The "standard" tax category is missing.');
            $method = new ShippingMethod($input->code, $input->name, $input->calculator, $config, $standard, $countries, $input->position, self::blank($input->description));
            $this->entityManager->persist($method);
        } else {
            $method = $repository->find($command->id) ?? throw NotFoundException::of('Shipping method', (string) $command->id);
        }
        $method->update($input->code, trim($input->name), self::blank($input->description), $input->calculator, $config, $countries, $input->position, $input->isActive);
        $this->entityManager->flush();

        return (int) $method->getId();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function useGateway(UsePaymentGateway $command): void
    {
        if (!\in_array($command->code, $this->gateways->codes(), true)) {
            throw ValidationException::forField('code', 'This payment gateway is not installed.');
        }
        $this->store()->usePaymentGateway($command->code);
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function addStaff(AddStoreStaff $command): void
    {
        $store = $this->store();
        $role = StoreRole::from($command->input->role);
        $this->assertMayGrant($role);
        $user = $this->entityManager->getRepository(StaffUser::class)->findOneBy(['email' => strtolower(trim($command->input->email))])
            ?? throw ValidationException::forField('email', 'No staff user has this email. A super-admin creates staff users under Platform.');
        if (null !== $this->entityManager->getRepository(StoreMembership::class)->findOneBy(['staffUser' => $user, 'store' => $store])) {
            throw ValidationException::forField('email', 'This person already works in this shop.');
        }
        $this->entityManager->persist(new StoreMembership($user, $store, $role));
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function changeRole(ChangeStaffRole $command): void
    {
        $membership = $this->membership($command->membershipId);
        $role = StoreRole::tryFrom($command->role) ?? throw ValidationException::forField('role', 'Choose a role.');
        $this->assertMayGrant($role);
        if (StoreRole::Owner === $membership->getRole() && StoreRole::Owner !== $role) {
            $this->assertNotLastOwner($membership);
        }
        $membership->changeRole($role);
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function removeStaff(RemoveStoreStaff $command): void
    {
        $membership = $this->membership($command->membershipId);
        if (StoreRole::Owner === $membership->getRole()) {
            $this->assertMayGrant(StoreRole::Owner);
            $this->assertNotLastOwner($membership);
        }
        $this->entityManager->remove($membership);
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function notifications(UpdateNotifications $command): void
    {
        $this->store()->changeNotifications(array_map(static fn ($v) => (bool) $v, $command->notifications));
        $this->entityManager->flush();
    }

    private function store(): Store
    {
        // The tenant context holds a detached-safe reference; re-read it managed for writes.
        $store = $this->tenantContext->requireStore();

        return $this->entityManager->find(Store::class, $store->getId()) ?? $store;
    }

    /** @return list<StoreDomain> */
    private function domains(Store $store): array
    {
        return $this->entityManager->getRepository(StoreDomain::class)->findBy(['store' => $store], ['id' => 'ASC']);
    }

    private function domain(int $id): StoreDomain
    {
        $domain = $this->entityManager->getRepository(StoreDomain::class)->find($id);

        return null !== $domain && $domain->getStore()->getId() === $this->store()->getId() ? $domain : throw NotFoundException::of('Domain', (string) $id);
    }

    /** @return list<StoreMembership> */
    private function memberships(Store $store): array
    {
        return $this->entityManager->getRepository(StoreMembership::class)->findBy(['store' => $store], ['id' => 'ASC']);
    }

    private function membership(int $id): StoreMembership
    {
        $membership = $this->entityManager->getRepository(StoreMembership::class)->find($id);

        return null !== $membership && $membership->getStore()->getId() === $this->store()->getId() ? $membership : throw NotFoundException::of('Staff member', (string) $id);
    }

    private function assertMayGrant(StoreRole $role): void
    {
        if (StoreRole::Owner === $role && !$this->authorization->isGranted('ROLE_STORE_OWNER') && !$this->authorization->isGranted('ROLE_SUPER_ADMIN')) {
            throw new \DomainException('Only an owner can add, remove or appoint owners.');
        }
    }

    private function assertNotLastOwner(StoreMembership $membership): void
    {
        $owners = array_filter($this->memberships($membership->getStore()), static fn (StoreMembership $m) => StoreRole::Owner === $m->getRole());
        if (1 === \count($owners)) {
            throw new \DomainException('A shop needs at least one owner.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingRow(ShippingMethod $method): array
    {
        $currency = $this->store()->getCurrencyCode();
        $decimal = static fn (mixed $cents) => \is_int($cents) ? Money::of($cents, $currency)->toDecimalString() : null;
        $config = $method->getConfig();

        return [
            'id' => $method->getId(),
            'code' => $method->getCode(),
            'name' => $method->getName(),
            'description' => $method->getDescription(),
            'calculator' => $method->getCalculator(),
            'amount' => $decimal($config['amount'] ?? null),
            'threshold' => $decimal($config['threshold'] ?? null),
            'brackets' => array_map(static fn (array $b) => [
                'upToKg' => \is_int($b['up_to_grams'] ?? null) ? (string) ($b['up_to_grams'] / 1000) : null,
                'amount' => $decimal($b['amount'] ?? 0),
            ], \is_array($config['brackets'] ?? null) ? $config['brackets'] : []),
            'allowedCountries' => $method->getAllowedCountries() ?? [],
            'position' => $method->getPosition(),
            'isActive' => $method->isActive(),
        ];
    }

    private static function blank(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return '' === $value ? null : $value;
    }
}
