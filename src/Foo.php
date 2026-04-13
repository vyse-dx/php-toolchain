<?php

declare(strict_types=1);

namespace Vyse\Toolchain;

final readonly class Foo
{
    public function __construct(
        private Bar $bar,
        private int $multiplier,
    ) {
    }

    public function test(): int
    {
        return $this->bar->test() * $this->multiplier;
    }
}
