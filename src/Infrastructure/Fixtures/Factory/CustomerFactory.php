<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Factory;

use App\Entity\Customer;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Customers without addresses; use CheckoutBuilder::customer() for a complete demo account.
 *
 * @extends PersistentObjectFactory<Customer>
 */
final class CustomerFactory extends PersistentObjectFactory
{
    public const PASSWORD = 'password';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Customer::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Customer $customer): void {
            $customer->changePassword($this->passwordHasher->hashPassword($customer, self::PASSWORD));
        });
    }
}
