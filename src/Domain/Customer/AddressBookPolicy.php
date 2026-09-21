<?php

declare(strict_types=1);

namespace App\Domain\Customer;

/**
 * A registered customer always keeps at least one billing and one delivery address, and the
 * default addresses must be usable for their role (decisions #35, #36).
 */
final class AddressBookPolicy
{
    /**
     * @param list<AddressBookEntry> $entries
     *
     * @throws AddressBookException
     */
    public function assertComplete(array $entries): void
    {
        $billing = array_filter($entries, static fn (AddressBookEntry $e): bool => $e->usableForBilling);
        if ([] === $billing) {
            throw new AddressBookException(AddressBookViolation::MissingBillingAddress, 'At least one billing address is required.');
        }

        $delivery = array_filter($entries, static fn (AddressBookEntry $e): bool => $e->usableForShipping);
        if ([] === $delivery) {
            throw new AddressBookException(AddressBookViolation::MissingDeliveryAddress, 'At least one delivery address is required.');
        }
    }

    /**
     * @param list<AddressBookEntry> $entries
     *
     * @throws AddressBookException when the address is the last billing or last delivery address
     */
    public function assertCanRemove(array $entries, string $addressId): void
    {
        $this->find($entries, $addressId);
        $this->assertComplete(array_values(array_filter($entries, static fn (AddressBookEntry $e): bool => $e->id !== $addressId)));
    }

    /**
     * @param list<AddressBookEntry> $entries
     *
     * @throws AddressBookException when the change would leave no billing or delivery address
     */
    public function assertCanUpdate(array $entries, AddressBookEntry $updated): void
    {
        $this->find($entries, $updated->id);
        $this->assertComplete(array_map(static fn (AddressBookEntry $e): AddressBookEntry => $e->id === $updated->id ? $updated : $e, $entries));
    }

    /**
     * @param list<AddressBookEntry> $entries
     *
     * @throws AddressBookException
     */
    public function assertDefaults(array $entries, string $defaultBillingId, string $defaultDeliveryId): void
    {
        if (!$this->find($entries, $defaultBillingId)->usableForBilling) {
            throw new AddressBookException(AddressBookViolation::DefaultNotUsable, 'The default billing address must be usable for billing.');
        }
        if (!$this->find($entries, $defaultDeliveryId)->usableForShipping) {
            throw new AddressBookException(AddressBookViolation::DefaultNotUsable, 'The default delivery address must be usable for delivery.');
        }
    }

    /**
     * @param list<AddressBookEntry> $entries
     */
    private function find(array $entries, string $addressId): AddressBookEntry
    {
        foreach ($entries as $entry) {
            if ($entry->id === $addressId) {
                return $entry;
            }
        }

        throw new AddressBookException(AddressBookViolation::UnknownAddress, sprintf('Address "%s" is not in this address book.', $addressId));
    }
}
