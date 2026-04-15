<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Vyse\Toolchain\Rector\AssertToStaticRector;

return RectorConfig::configure()
    ->withAutoloadPaths([
        __DIR__ . '/test/Unit/Rector/stubs',
    ])
    ->withCache(__DIR__ . '/.cache/rector')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ])
    ->withRules([
        AssertToStaticRector::class,
    ])
;
