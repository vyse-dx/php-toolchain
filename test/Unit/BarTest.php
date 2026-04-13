<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Vyse\Toolchain\Bar;

class BarTest extends TestCase
{
    public function testSomething(): void
    {
        $bar = new Bar(42);
        self::assertSame(42, $bar->test());
    }
}
