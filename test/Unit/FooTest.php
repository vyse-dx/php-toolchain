<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Toolchain\Bar;
use Vyse\Toolchain\Foo;

class FooTest extends TestCase
{
    private Foo $foo;
    private MockObject & Bar $bar;

    public function setUp(): void
    {
        $this->bar = $this->createMock(Bar::class);
        $this->foo = new Foo($this->bar, 2);
    }

    public function testSomething(): void
    {
        $this->bar->method('test')->willReturn(42);
        self::assertSame(84, $this->foo->test());
    }
}
