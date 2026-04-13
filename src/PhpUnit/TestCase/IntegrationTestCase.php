<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpUnit\TestCase;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * Auto-wires a class for integration testing.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $overrides
     * @return T
     * @throws ReflectionException|RuntimeException
     */
    protected function make(
        string $className,
        array $overrides = [],
    ): object {
        /** @var T $instance */
        $instance = $this->resolveClass($className, $overrides);

        return $instance;
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $overrides
     * @throws ReflectionException|RuntimeException
     */
    private function resolveClass(
        string $className,
        array $overrides,
    ): object {
        if (array_key_exists($className, $overrides)) {
            $override = $overrides[$className];
            if (!is_object($override)) {
                throw new RuntimeException("Override for {$className} must be an object.");
            }

            return $override;
        }

        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot instantiate {$className}. Did you forget to provide a mock?");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter, $overrides);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        array $overrides,
    ): mixed {
        $name = $parameter->getName();

        if (array_key_exists($name, $overrides)) {
            return $overrides[$name];
        }

        $type = $parameter->getType();

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new RuntimeException("Cannot resolve untyped parameter \${$name}");
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->resolveNamedType($type, $parameter, $overrides);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->resolveUnionType($type, $parameter, $overrides);
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw new RuntimeException("Intersection types are not supported. DIAF or provide an explicit override for \${$name}.");
        }

        throw new RuntimeException("Unhandled parameter type for \${$name}.");
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function resolveNamedType(
        ReflectionNamedType $type,
        ReflectionParameter $parameter,
        array $overrides,
    ): mixed {
        $typeName = $type->getName();

        if (array_key_exists($typeName, $overrides)) {
            return $overrides[$typeName];
        }

        if ($type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new RuntimeException("Missing scalar override for \${$parameter->getName()}");
        }

        // Inform PHPStan this string is guaranteed to be a class/interface
        assert(class_exists($typeName) || interface_exists($typeName));

        return $this->resolveClass($typeName, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function resolveUnionType(
        ReflectionUnionType $type,
        ReflectionParameter $parameter,
        array $overrides,
    ): mixed {
        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType && array_key_exists($unionType->getName(), $overrides)) {
                return $overrides[$unionType->getName()];
            }
        }

        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                $typeName = $unionType->getName();
                assert(class_exists($typeName) || interface_exists($typeName));

                try {
                    return $this->resolveClass($typeName, $overrides);
                } catch (RuntimeException) {
                    continue;
                }
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException("Cannot resolve union type for \${$parameter->getName()}.");
    }
}
