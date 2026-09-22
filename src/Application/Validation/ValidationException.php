<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Business validation that needs the database (e.g. "this SKU is already used"). Answered as
 * 422 problem+json with violations, exactly like Symfony Validator errors, so the frontend shows
 * each message under its field.
 */
final class ValidationException extends \RuntimeException
{
    /**
     * @param list<array{propertyPath: string, message: string}> $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode(' ', array_column($violations, 'message')));
    }

    public static function forField(string $propertyPath, string $message): self
    {
        return new self([['propertyPath' => $propertyPath, 'message' => $message]]);
    }
}
