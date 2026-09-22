<?php

declare(strict_types=1);

namespace App\Application\Ordering;

/**
 * The order cannot take this transition now (no such edge, or a guard said no). Answered as 422.
 */
final class TransitionNotAllowedException extends \DomainException
{
    /**
     * @param list<string> $reasons
     */
    public static function for(string $transition, string $state, array $reasons): self
    {
        $label = str_replace('_', ' ', $transition);

        return new self([] === $reasons
            ? sprintf('The order cannot be %s now (it is %s).', self::pastTense($label), str_replace('_', ' ', $state))
            : implode(' ', $reasons));
    }

    private static function pastTense(string $label): string
    {
        return match ($label) {
            'pay' => 'marked as paid',
            'ship' => 'shipped',
            'deliver' => 'marked as delivered',
            'cancel' => 'cancelled',
            'refund' => 'refunded',
            'start processing' => 'processed',
            default => $label.'ed',
        };
    }
}
