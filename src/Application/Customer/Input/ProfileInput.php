<?php

declare(strict_types=1);

namespace App\Application\Customer\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ProfileInput
{
    #[Assert\NotBlank(message: 'Enter your first name.')]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Enter your last name.')]
    #[Assert\Length(max: 100)]
    public string $lastName = '';

    #[Assert\Length(max: 32)]
    #[Assert\Regex(pattern: '/^\+?[0-9 ()-]{6,}$/', message: 'Enter a phone number like +31 20 123 4567.')]
    public ?string $phone = null;
}
