<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * How a product specification is entered and filtered (attribute.type).
 */
enum AttributeType: string
{
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Number = 'number';
    case Text = 'text';

    public function usesOptions(): bool
    {
        return self::Select === $this || self::MultiSelect === $this;
    }
}
