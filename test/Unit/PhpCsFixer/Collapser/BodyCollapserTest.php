<?php

declare(strict_types=1);

namespace App\Toolchain\Test\PhpCsFixer\Collapser;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vyse\Toolchain\PhpCsFixer\Collapser\BodyCollapser;

class BodyCollapserTest extends TestCase
{
    private BodyCollapser $bodyCollapser;

    public function setUp(): void
    {
        $this->bodyCollapser = new BodyCollapser;
    }

    public function testCollapsesMultiLineEmptyBody(): void
    {
        $code = <<<'PHP'
<?php
function foo() {
}
PHP;
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        ($this->bodyCollapser)(
            $tokens,
            $index,
        );

        $expected = <<<'PHP'
<?php
function foo() {}
PHP;

        self::assertSame(
            $expected,
            $tokens->generateCode(),
        );
    }

    public function testPullsBraceToSameLine(): void
    {
        $code = <<<'PHP'
<?php
function foo()
{}
PHP;
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        ($this->bodyCollapser)(
            $tokens,
            $index,
        );

        $expected = <<<'PHP'
<?php
function foo() {}
PHP;

        self::assertSame(
            $expected,
            $tokens->generateCode(),
        );
    }

    public function testHandlesReturnTypeHints(): void
    {
        $code = <<<'PHP'
<?php
function foo(): void
{}
PHP;
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        ($this->bodyCollapser)(
            $tokens,
            $index,
        );

        $expected = <<<'PHP'
<?php
function foo(): void {}
PHP;

        self::assertSame(
            $expected,
            $tokens->generateCode(),
        );
    }

    private function findFunctionIndex(
        Tokens $tokens,
    ): int {
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_FUNCTION)) {
                return $index;
            }
        }

        throw new RuntimeException('No function token found in test code.');
    }
}
