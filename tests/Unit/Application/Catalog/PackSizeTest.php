<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Catalog;

use App\Application\Catalog\PackSize;
use PHPUnit\Framework\TestCase;

final class PackSizeTest extends TestCase
{
    public function testLabels(): void
    {
        self::assertSame('1 L', PackSize::label(1000));
        self::assertSame('208 L', PackSize::label(208000));
        self::assertSame('2.5 L', PackSize::label(2500));
        self::assertSame('400 ml', PackSize::label(400));
    }
}
