<?php

declare(strict_types=1);

namespace App\Application\Customer\Account;

use App\Application\Customer\AddressWriter;
use App\Application\Customer\CurrentCustomerInterface;
use App\Application\Customer\Port\CountryRepositoryInterface;
use App\Application\Customer\Port\CustomerRepositoryInterface;
use App\Application\Customer\View\AccountView;
use App\Application\Customer\View\AddressView;
use App\Application\Exception\NotFoundException;
use App\Application\Ordering\Port\OrderRepositoryInterface;
use App\Application\Ordering\Port\PaymentRepositoryInterface;
use App\Application\Ordering\View\OrderView;
use App\Application\Validation\ValidationException;
use App\Domain\Customer\AddressBookEntry;
use App\Domain\Customer\AddressBookPolicy;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The customer account: profile, password, address book (AddressBookPolicy) and own orders.
 */
final readonly class AccountHandler
{
    public function __construct(
        private CurrentCustomerInterface $currentCustomer,
        private CustomerRepositoryInterface $customers,
        private CountryRepositoryInterface $countries,
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
        private AddressWriter $addressWriter,
        private AddressBookPolicy $policy,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function account(GetAccount $query): AccountView
    {
        $customer = $this->customer();
        $recent = $this->orders->placedOrdersOf($customer, 1, 3);

        return new AccountView(
            [
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'memberSince' => $customer->getCreatedAt()->format(\DATE_ATOM),
            ],
            $this->addresses($customer),
            array_map(fn ($order) => OrderView::from($order, $this->payments->latestFor($order)), $recent['items']),
            $recent['total'],
            array_map(static fn ($c) => ['code' => $c->getCode(), 'name' => $c->getName()], $this->countries->findAll()),
        );
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function updateProfile(UpdateProfile $command): void
    {
        $customer = $this->customer();
        $phone = trim($command->input->phone ?? '');
        $customer->updateProfile(trim($command->input->firstName), trim($command->input->lastName), '' === $phone ? null : $phone);
        $this->customers->save($customer);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function changePassword(ChangePassword $command): void
    {
        $customer = $this->customer();
        if (!$this->passwordHasher->isPasswordValid($customer, $command->input->currentPassword)) {
            throw ValidationException::forField('currentPassword', 'This is not your current password.');
        }
        $customer->changePassword($this->passwordHasher->hashPassword($customer, $command->input->newPassword));
        $this->customers->save($customer);
    }

    /**
     * @return list<AddressView>
     */
    #[AsMessageHandler(bus: 'command.bus')]
    public function saveAddress(SaveAddress $command): array
    {
        $customer = $this->customer();
        if (null === $command->id) {
            $address = new CustomerAddress($customer, $this->addressWriter->country($command->input->countryCode));
            $this->addressWriter->write($command->input, $address);
            $customer->addAddress($address);
        } else {
            $address = $this->address($customer, $command->id);
            // Check the rule on the changed flags before touching the entity.
            $this->policy->assertCanUpdate($customer->entries(), new AddressBookEntry($command->id, $command->input->usableForBilling, $command->input->usableForShipping));
            $this->addressWriter->write($command->input, $address);
        }
        $customer->repairDefaults();
        $this->customers->save($customer);

        return $this->addresses($customer);
    }

    /**
     * @return list<AddressView>
     */
    #[AsMessageHandler(bus: 'command.bus')]
    public function deleteAddress(DeleteAddress $command): array
    {
        $customer = $this->customer();
        $customer->removeAddress($this->address($customer, $command->id), $this->policy);
        $this->customers->save($customer);

        return $this->addresses($customer);
    }

    /**
     * @return list<AddressView>
     */
    #[AsMessageHandler(bus: 'command.bus')]
    public function setDefaults(SetDefaultAddresses $command): array
    {
        $customer = $this->customer();
        $customer->setDefaults($this->address($customer, $command->input->billingId), $this->address($customer, $command->input->shippingId), $this->policy);
        $this->customers->save($customer);

        return $this->addresses($customer);
    }

    /**
     * @return array{items: list<OrderView>, total: int, page: int}
     */
    #[AsMessageHandler(bus: 'query.bus')]
    public function orders(ListMyOrders $query): array
    {
        $page = max(1, $query->page);
        $result = $this->orders->placedOrdersOf($this->customer(), $page, 10);

        return [
            'items' => array_map(fn ($order) => OrderView::from($order, $this->payments->latestFor($order)), $result['items']),
            'total' => $result['total'],
            'page' => $page,
        ];
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function order(GetMyOrder $query): OrderView
    {
        $order = Uuid::isValid($query->id) ? $this->orders->findByPublicId(Uuid::fromString($query->id)) : null;
        if (null === $order || $order->isDraft() || $order->getCustomer() !== $this->customer()) {
            throw NotFoundException::of('Order', $query->id);
        }

        return OrderView::from($order, $this->payments->latestFor($order));
    }

    private function customer(): Customer
    {
        return $this->currentCustomer->get() ?? throw new \LogicException('The account API needs a logged-in customer (access_control).');
    }

    private function address(Customer $customer, string $id): CustomerAddress
    {
        return $customer->findAddress($id) ?? throw NotFoundException::of('Address', $id);
    }

    /**
     * @return list<AddressView>
     */
    private function addresses(Customer $customer): array
    {
        return array_values(array_map(AddressView::from(...), $customer->getAddresses()->toArray()));
    }
}
