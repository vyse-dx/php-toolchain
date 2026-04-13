<?php

declare(strict_types=1);

namespace Vyse\Toolchain\Test\Integration;

use Vyse\Toolchain\Bar;
use Vyse\Toolchain\Foo;
use Vyse\Toolchain\PhpUnit\TestCase\IntegrationTestCase;

final class FooTest extends IntegrationTestCase
{
    public function test_it_autowires_deep_dependencies_and_injects_scalars(): void
    {
        $foo = $this->make(Foo::class, [
            'baseValue' => 10,
            'multiplier' => 2,
        ]);

        self::assertSame(20, $foo->test());
    }

    public function test_it_accepts_specific_mocks_while_still_injecting_scalars(): void
    {
        $mockBar = $this->createMock(Bar::class);
        $mockBar->expects(self::once())
            ->method('test')
            ->willReturn(5)
        ;

        $foo = $this->make(Foo::class, [
            Bar::class => $mockBar,
            'multiplier' => 3,
        ]);

        self::assertSame(15, $foo->test());
    }
}
