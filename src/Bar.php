<?php

declare(strict_types=1);

namespace Vyse\Toolchain;

final readonly class Bar
{
    public function __construct(
        private int $baseValue,
    ) {
    }

    public function test(): int
    {
        return $this->baseValue;
    }
}
