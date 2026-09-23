<?php

declare(strict_types=1);

namespace App\Application\Content;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The contact form (jQuery validation in the browser, these rules on the server).
 */
final class ContactInput
{
    public const SUBJECTS = ['order', 'product', 'business', 'other'];

    #[Assert\NotBlank(message: 'Enter your name.')]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Enter your email address.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Choice(choices: self::SUBJECTS, message: 'Choose a subject.')]
    public string $subject = 'other';

    #[Assert\Length(max: 32)]
    #[Assert\Regex(pattern: '/^[A-Z]{2,16}-\d{6}$/i', message: 'An order number looks like AUTO-000123.')]
    public ?string $orderNumber = null;

    #[Assert\NotBlank(message: 'Write your message.')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Please write at least 10 characters.')]
    public string $message = '';

    /** Honeypot: people never see this field, bots fill it in. */
    public ?string $website = null;
}
