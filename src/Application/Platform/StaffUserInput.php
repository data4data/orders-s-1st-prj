<?php

declare(strict_types=1);

namespace App\Application\Platform;

use Symfony\Component\Validator\Constraints as Assert;

final class StaffUserInput
{
    #[Assert\NotBlank(message: 'Enter an email address.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Enter the first name.')]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Enter the last name.')]
    public string $lastName = '';

    #[Assert\NotBlank(message: 'Choose a start password.')]
    #[Assert\Length(min: 8, minMessage: 'Use at least 8 characters.')]
    public string $password = '';

    public bool $superAdmin = false;
}
