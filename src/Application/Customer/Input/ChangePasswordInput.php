<?php

declare(strict_types=1);

namespace App\Application\Customer\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordInput
{
    #[Assert\NotBlank(message: 'Enter your current password.')]
    public string $currentPassword = '';

    #[Assert\NotBlank(message: 'Choose a new password.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Use at least 8 characters.')]
    public string $newPassword = '';
}
