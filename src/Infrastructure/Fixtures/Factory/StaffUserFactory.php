<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Factory;

use App\Entity\StaffUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StaffUser>
 */
final class StaffUserFactory extends PersistentObjectFactory
{
    /** Development and test password for every generated staff user. */
    public const PASSWORD = 'password';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return StaffUser::class;
    }

    public function superAdmin(): self
    {
        return $this->with(['isSuperAdmin' => true]);
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'isSuperAdmin' => false,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (StaffUser $user): void {
            $user->changePassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        });
    }
}
