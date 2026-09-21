<?php

declare(strict_types=1);

namespace App\Domain\Customer;

final class AddressBookException extends \DomainException
{
    public function __construct(public readonly AddressBookViolation $violation, string $message)
    {
        parent::__construct($message);
    }
}
