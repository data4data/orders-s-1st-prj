<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final class StaffMemberInput
{
    #[Assert\NotBlank(message: 'Enter the email of an existing staff user.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    public string $email = '';

    #[Assert\Choice(choices: ['owner', 'manager', 'staff'], message: 'Choose a role.')]
    public string $role = 'staff';
}
