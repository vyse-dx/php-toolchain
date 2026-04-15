<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Vyse\Toolchain\Rector\AssertToStaticRector;

return RectorConfig::configure()
    ->withAutoloadPaths([
        __DIR__ . '/../../stubs',
    ])
    ->withRules([
        AssertToStaticRector::class,
    ])
;
