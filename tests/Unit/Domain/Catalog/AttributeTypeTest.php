<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalog;

use App\Domain\Catalog\AttributeType;
use PHPUnit\Framework\TestCase;

final class AttributeTypeTest extends TestCase
{
    public function testOnlySelectTypesUseOptions(): void
    {
        self::assertTrue(AttributeType::Select->usesOptions());
        self::assertTrue(AttributeType::MultiSelect->usesOptions());
        self::assertFalse(AttributeType::Number->usesOptions());
        self::assertFalse(AttributeType::Text->usesOptions());
    }
}
