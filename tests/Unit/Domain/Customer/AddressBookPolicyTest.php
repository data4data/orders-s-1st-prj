<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Customer;

use App\Domain\Customer\AddressBookEntry;
use App\Domain\Customer\AddressBookException;
use App\Domain\Customer\AddressBookPolicy;
use App\Domain\Customer\AddressBookViolation;
use PHPUnit\Framework\TestCase;

final class AddressBookPolicyTest extends TestCase
{
    private AddressBookPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new AddressBookPolicy();
    }

    public function testOneAddressForBothRolesIsComplete(): void
    {
        $this->policy->assertComplete([new AddressBookEntry('home', true, true)]);
        $this->addToAssertionCount(1);
    }

    public function testBillingIsRequired(): void
    {
        $this->assertViolation(AddressBookViolation::MissingBillingAddress, fn () => $this->policy->assertComplete([new AddressBookEntry('a', false, true)]));
    }

    public function testDeliveryIsRequired(): void
    {
        $this->assertViolation(AddressBookViolation::MissingDeliveryAddress, fn () => $this->policy->assertComplete([new AddressBookEntry('a', true, false)]));
    }

    public function testTheLastBillingAddressCannotBeRemoved(): void
    {
        $book = [new AddressBookEntry('office', true, false), new AddressBookEntry('workshop', false, true)];

        $this->assertViolation(AddressBookViolation::MissingBillingAddress, fn () => $this->policy->assertCanRemove($book, 'office'));
    }

    public function testASpareAddressCanBeRemoved(): void
    {
        $book = [new AddressBookEntry('home', true, true), new AddressBookEntry('workshop', false, true)];

        $this->policy->assertCanRemove($book, 'workshop');
        $this->addToAssertionCount(1);
    }

    public function testUnknownAddressesAreReported(): void
    {
        $this->assertViolation(AddressBookViolation::UnknownAddress, fn () => $this->policy->assertCanRemove([new AddressBookEntry('home', true, true)], 'nope'));
    }

    public function testAnUpdateMayNotDropTheLastDeliveryAddress(): void
    {
        $book = [new AddressBookEntry('home', true, true), new AddressBookEntry('office', true, false)];

        $this->assertViolation(AddressBookViolation::MissingDeliveryAddress, fn () => $this->policy->assertCanUpdate($book, new AddressBookEntry('home', true, false)));

        $this->policy->assertCanUpdate($book, new AddressBookEntry('office', true, true));
    }

    public function testDefaultsMustBeUsableForTheirRole(): void
    {
        $book = [new AddressBookEntry('office', true, false), new AddressBookEntry('workshop', false, true)];

        $this->policy->assertDefaults($book, 'office', 'workshop');
        $this->assertViolation(AddressBookViolation::DefaultNotUsable, fn () => $this->policy->assertDefaults($book, 'workshop', 'workshop'));
        $this->assertViolation(AddressBookViolation::DefaultNotUsable, fn () => $this->policy->assertDefaults($book, 'office', 'office'));
    }

    public function testAnAddressMustHaveARole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AddressBookEntry('x', false, false);
    }

    private function assertViolation(AddressBookViolation $expected, callable $action): void
    {
        try {
            $action();
            self::fail('Expected an address book violation.');
        } catch (AddressBookException $exception) {
            self::assertSame($expected, $exception->violation);
        }
    }
}
