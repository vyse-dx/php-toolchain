<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Vyse\Toolchain\PhpUnit\TestCase\IntegrationTestCase;

/**
 * @implements Rule<MethodCall>
 */
final class IntegrationTestCaseMakeRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(
        Node $node,
        Scope $scope,
    ): array {
        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'make') {
            return [];
        }

        $callerType = $scope->getType($node->var);
        $testCaseType = new ObjectType(IntegrationTestCase::class);

        if (!$testCaseType->isSuperTypeOf($callerType)->yes()) {
            return [];
        }

        if (count($node->getArgs()) < 2) {
            return [];
        }

        $classArgType = $scope->getType($node->getArgs()[0]->value);
        $constantStrings = $classArgType->getConstantStrings();

        if (count($constantStrings) === 0) {
            return [];
        }

        $targetClassName = $constantStrings[0]->getValue();

        if (!$this->reflectionProvider->hasClass($targetClassName)) {
            return [];
        }

        $targetClassReflection = $this->reflectionProvider->getClass($targetClassName);

        $validKeys = $this->getValidKeys($targetClassReflection);

        $errors = [];
        $overridesArgType = $scope->getType($node->getArgs()[1]->value);

        foreach ($overridesArgType->getConstantArrays() as $constantArray) {
            foreach ($constantArray->getKeyTypes() as $keyType) {

                $keyStrings = $keyType->getConstantStrings();
                if (count($keyStrings) === 0) {
                    continue;
                }

                $overrideKey = $keyStrings[0]->getValue();

                if (!isset($validKeys[$overrideKey])) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf(
                            'Test setup error: Key "%s" does not match any constructor parameter or deep dependency of %s.',
                            $overrideKey,
                            $targetClassName,
                        ),
                    )->identifier('vyse.toolchain.makeOverride')->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Recursively walks the dependency tree to find all valid constructor parameters and class names.
     *
     * @param array<string, true> $visitedClasses Keeps track of visited classes to prevent infinite loops
     * @return array<string, true> Map of valid override keys
     */
    private function getValidKeys(
        ClassReflection $classReflection,
        array &$visitedClasses = [],
    ): array {
        $className = $classReflection->getName();

        if (isset($visitedClasses[$className])) {
            return [];
        }
        $visitedClasses[$className] = true;

        if (!$classReflection->hasConstructor()) {
            return [];
        }

        $validKeys = [];
        $constructor = $classReflection->getConstructor();

        foreach ($constructor->getVariants()[0]->getParameters() as $parameter) {
            $validKeys[$parameter->getName()] = true;

            foreach ($parameter->getType()->getReferencedClasses() as $referencedClass) {
                $validKeys[$referencedClass] = true;

                if ($this->reflectionProvider->hasClass($referencedClass)) {
                    $dependencyReflection = $this->reflectionProvider->getClass($referencedClass);

                    $validKeys = array_merge($validKeys, $this->getValidKeys($dependencyReflection, $visitedClasses));
                }
            }
        }

        return $validKeys;
    }
}
