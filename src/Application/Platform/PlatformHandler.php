<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Application\Exception\NotFoundException;
use App\Application\Validation\ValidationException;
use App\Domain\Tax\TaxRate as DomainTaxRate;
use App\Domain\Tax\TaxRatePeriod;
use App\Domain\Tax\TaxRateTable;
use App\Entity\Country;
use App\Entity\StaffUser;
use App\Entity\Store;
use App\Entity\StoreDomain;
use App\Entity\TaxCategory;
use App\Entity\TaxRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Platform pages (super-admin only, checked by the controller): platform tables, not tenant data.
 */
final readonly class PlatformHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SystemStatusInterface $system,
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function overview(GetPlatformOverview $query): array
    {
        $domains = [];
        foreach ($this->entityManager->getRepository(StoreDomain::class)->findBy([], ['id' => 'ASC']) as $domain) {
            $domains[(int) $domain->getStore()->getId()][] = ['host' => $domain->getHost(), 'isPrimary' => $domain->isPrimary()];
        }

        $rates = [];
        foreach ($this->entityManager->getRepository(TaxRate::class)->findBy([], ['validFrom' => 'DESC']) as $rate) {
            $rates[$rate->getCountry()->getCode()][] = [
                'id' => $rate->getId(),
                'taxCategory' => $rate->getTaxCategory()->getCode(),
                'rate' => $rate->getRate(),
                'validFrom' => $rate->getValidFrom()->format('Y-m-d'),
                'validTo' => $rate->getValidTo()?->format('Y-m-d'),
            ];
        }

        return [
            'stores' => array_map(static fn (Store $s) => [
                'id' => $s->getId(),
                'code' => $s->getCode(),
                'name' => $s->getName(),
                'country' => $s->getCountry()->getCode(),
                'currency' => $s->getCurrencyCode(),
                'orderNumberPrefix' => $s->getOrderNumberPrefix(),
                'isActive' => $s->isActive(),
                'domains' => $domains[(int) $s->getId()] ?? [],
            ], $this->entityManager->getRepository(Store::class)->findBy([], ['name' => 'ASC'])),
            'countries' => array_map(static fn (Country $c) => [
                'code' => $c->getCode(),
                'name' => $c->getName(),
                'isEu' => $c->isEu(),
                'rates' => $rates[$c->getCode()] ?? [],
            ], $this->entityManager->getRepository(Country::class)->findBy([], ['name' => 'ASC'])),
            'taxCategories' => array_map(static fn (TaxCategory $t) => ['id' => $t->getId(), 'code' => $t->getCode(), 'name' => $t->getName()], $this->entityManager->getRepository(TaxCategory::class)->findBy([], ['code' => 'ASC'])),
            'staffUsers' => array_map(fn (StaffUser $u) => [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'name' => trim($u->getFirstName().' '.$u->getLastName()),
                'superAdmin' => $u->isSuperAdmin(),
                'lastLoginAt' => $u->getLastLoginAt()?->format(\DATE_ATOM),
                'isMe' => $this->tokens->getToken()?->getUser() === $u,
            ], $this->entityManager->getRepository(StaffUser::class)->findBy([], ['email' => 'ASC'])),
        ];
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function createStore(CreateStore $command): int
    {
        $input = $command->input;
        if (null !== $this->entityManager->getRepository(Store::class)->findOneBy(['code' => $input->code])) {
            throw ValidationException::forField('code', 'Another shop already uses this code.');
        }
        $host = strtolower(trim($input->host));
        if (null !== $this->entityManager->getRepository(StoreDomain::class)->findOneBy(['host' => $host])) {
            throw ValidationException::forField('host', 'This host name is already used by a shop.');
        }
        $country = $this->entityManager->find(Country::class, strtoupper($input->countryCode)) ?? throw ValidationException::forField('countryCode', 'Choose a country.');

        $store = new Store($input->code, trim($input->name), $country, $input->currencyCode, strtoupper($input->orderNumberPrefix));
        $this->entityManager->persist($store);
        $this->entityManager->persist(new StoreDomain($store, $host, true));
        $this->entityManager->flush();

        return (int) $store->getId();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function setStoreActive(SetStoreActive $command): void
    {
        $store = $this->entityManager->find(Store::class, $command->id) ?? throw NotFoundException::of('Store', (string) $command->id);
        $command->active ? $store->activate() : $store->deactivate();
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function addCountry(AddCountry $command): void
    {
        $code = strtoupper(trim($command->code));
        if (1 !== preg_match('/^[A-Z]{2}$/', $code)) {
            throw ValidationException::forField('code', 'Use the two-letter country code, e.g. FR.');
        }
        if ('' === trim($command->name)) {
            throw ValidationException::forField('name', 'Enter the country name.');
        }
        if (null !== $this->entityManager->find(Country::class, $code)) {
            throw ValidationException::forField('code', 'This country already exists.');
        }
        $this->entityManager->persist(new Country($code, trim($command->name), $command->isEu));
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function addTaxRate(AddTaxRate $command): void
    {
        $input = $command->input;
        $country = $this->entityManager->find(Country::class, strtoupper($input->countryCode)) ?? throw ValidationException::forField('countryCode', 'Choose a country.');
        $category = $this->entityManager->getRepository(TaxCategory::class)->findOneBy(['code' => $input->taxCategory]) ?? throw ValidationException::forField('taxCategory', 'Choose a tax category.');
        $from = new \DateTimeImmutable($input->validFrom);
        $to = null !== $input->validTo && '' !== $input->validTo ? new \DateTimeImmutable($input->validTo) : null;
        if (null !== $to && $to < $from) {
            throw ValidationException::forField('validTo', 'The last day must be after the first day.');
        }

        // The pure rate table rejects overlapping periods (decision #10).
        $periods = [new TaxRatePeriod($country->getCode(), $category->getCode(), DomainTaxRate::of($input->rate), $from, $to)];
        foreach ($this->entityManager->getRepository(TaxRate::class)->findBy(['country' => $country, 'taxCategory' => $category]) as $existing) {
            $periods[] = new TaxRatePeriod($country->getCode(), $category->getCode(), DomainTaxRate::of($existing->getRate()), $existing->getValidFrom(), $existing->getValidTo());
        }
        try {
            new TaxRateTable($periods);
        } catch (\InvalidArgumentException|\DomainException) {
            throw ValidationException::forField('validFrom', 'This period overlaps an existing rate. End the current rate first (set its last day).');
        }

        $this->entityManager->persist(new TaxRate($country, $category, number_format((float) $input->rate, 2, '.', ''), $from, $to));
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function createTaxCategory(CreateTaxCategory $command): void
    {
        $code = strtolower(trim($command->code));
        if (1 !== preg_match('/^[a-z_]{2,32}$/', $code)) {
            throw ValidationException::forField('code', 'Use lower-case letters and _, e.g. reduced.');
        }
        if (null !== $this->entityManager->getRepository(TaxCategory::class)->findOneBy(['code' => $code])) {
            throw ValidationException::forField('code', 'This tax category already exists.');
        }
        $this->entityManager->persist(new TaxCategory($code, '' !== trim($command->name) ? trim($command->name) : $code));
        $this->entityManager->flush();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function createStaffUser(CreateStaffUser $command): int
    {
        $input = $command->input;
        if (null !== $this->entityManager->getRepository(StaffUser::class)->findOneBy(['email' => strtolower(trim($input->email))])) {
            throw ValidationException::forField('email', 'A staff user with this email already exists.');
        }
        $user = new StaffUser($input->email, trim($input->firstName), trim($input->lastName), $input->superAdmin);
        $user->changePassword($this->passwordHasher->hashPassword($user, $input->password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return (int) $user->getId();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function setSuperAdmin(SetSuperAdmin $command): void
    {
        $user = $this->entityManager->find(StaffUser::class, $command->id) ?? throw NotFoundException::of('Staff user', (string) $command->id);
        if (!$command->superAdmin && $this->tokens->getToken()?->getUser() === $user) {
            throw new \DomainException('You cannot remove your own super-admin rights.');
        }
        $user->grantSuperAdmin($command->superAdmin);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function system(GetSystemStatus $query): array
    {
        return ['queues' => $this->system->queues(), 'failed' => $this->system->failedMessages(50), 'webhooks' => $this->system->recentWebhooks(20)];
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function retry(RetryFailedMessage $command): void
    {
        if (!$this->system->retry($command->id)) {
            throw NotFoundException::of('Failed message', (string) $command->id);
        }
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteFailedMessage $command): void
    {
        if (!$this->system->delete($command->id)) {
            throw NotFoundException::of('Failed message', (string) $command->id);
        }
    }
}
