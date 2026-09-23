<?php

declare(strict_types=1);

namespace App\UI\Http\UiKit;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Demo form data for the UI kit pages (dev only): shows how server-side validation errors
 * appear under the fields in both frontends.
 */
final class UiKitContactData
{
    #[Assert\NotBlank(message: 'Enter your name.')]
    #[Assert\Length(max: 100, maxMessage: 'Use at most {{ limit }} characters.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Enter your email address.')]
    #[Assert\Email(message: 'Enter a full email address, e.g. jan@example.com.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Enter a postcode.')]
    #[Assert\Regex(pattern: '/^\d{4}\s?[A-Za-z]{2}$/', message: 'Use the format 1234 AB.')]
    public string $postcode = '';

    #[Assert\NotBlank(message: 'Write a message.')]
    #[Assert\Length(min: 10, minMessage: 'Write at least {{ limit }} characters.')]
    public string $message = '';
}
