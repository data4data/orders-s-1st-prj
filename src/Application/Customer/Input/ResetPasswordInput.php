<?php

declare(strict_types=1);

namespace App\Application\Customer\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordInput
{
    #[Assert\NotBlank(message: 'Choose a new password.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Use at least 8 characters.')]
    public string $password = '';

    #[Assert\NotBlank(message: 'Repeat the new password.')]
    #[Assert\EqualTo(propertyPath: 'password', message: 'The passwords do not match.')]
    public string $repeatPassword = '';
}
