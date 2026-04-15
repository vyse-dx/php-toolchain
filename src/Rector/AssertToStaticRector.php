<?php

declare(strict_types=1);

namespace Vyse\Toolchain\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class AssertToStaticRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Converts $this->assert... to self::assert...', [
            new CodeSample(
                <<<'CODE_SAMPLE'
$this->assertTrue($foo);
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
self::assertTrue($foo);
CODE_SAMPLE
            )
        ]);
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(
        Node $node,
    ): ?Node {
        // Satisfies PHPStan's type narrowing without violating contravariance
        if (!$node instanceof MethodCall) {
            return null;
        }

        if (!$this->isName($node->var, 'this')) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if ($methodName === null || !str_starts_with($methodName, 'assert')) {
            return null;
        }

        if (!$this->isObjectType($node->var, new ObjectType('PHPUnit\Framework\TestCase'))) {
            return null;
        }

        return new StaticCall(
            new Name('self'),
            $node->name,
            $node->args,
        );
    }
}
