<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpUnit\Bypass;

use InvalidArgumentException;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class PhpUnitExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        PhpUnitMutator::denyPaths([
            '*/vendor/phpunit/*',
        ]);

        $bypassReadOnly = !$parameters->has('bypassReadOnly') || $this->parseBoolean($parameters->get('bypassReadOnly'));
        $bypassFinal = !$parameters->has('bypassFinal') || $this->parseBoolean($parameters->get('bypassFinal'));
        PhpUnitMutator::enable($bypassReadOnly, $bypassFinal);

        if ($parameters->has('cacheDirectory')) {
            PhpUnitMutator::setCacheDirectory($parameters->get('cacheDirectory'));
        }
    }

    private function parseBoolean(
        string $value,
    ): bool {
        $value = strtolower($value);
        if (in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        } elseif (in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }

        throw new InvalidArgumentException("Invalid boolean-like value: $value");

    }
}
