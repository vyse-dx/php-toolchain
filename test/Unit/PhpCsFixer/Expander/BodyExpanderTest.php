<?php

declare(strict_types=1);

namespace App\Toolchain\Test\PhpCsFixer\Expander;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vyse\Toolchain\PhpCsFixer\Detector\IndentationDetector;
use Vyse\Toolchain\PhpCsFixer\Expander\BodyExpander;

class BodyExpanderTest extends TestCase
{
    private BodyExpander $bodyExpander;

    public function setUp(): void
    {
        // We can use the real detector since it has no side effects
        $this->bodyExpander = new BodyExpander(
            new IndentationDetector,
        );
    }

    public function testExpandsSingleLineBodyToMultiLine(): void
    {
        $code = '<?php
    function foo() {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        ($this->bodyExpander)(
            $tokens,
            $index,
        );

        $expected = <<<'PHP'
<?php
    function foo() {
    }
PHP;

        self::assertSame(
            $expected,
            $tokens->generateCode(),
        );
    }

    public function testIgnoresAlreadyMultiLineBody(): void
    {
        $code = <<<'PHP'
<?php
function foo() {
}
PHP;
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        ($this->bodyExpander)(
            $tokens,
            $index,
        );

        // Should remain exactly the same
        self::assertSame(
            $code,
            $tokens->generateCode(),
        );
    }

    public function testExpandsWithCorrectIndentationLevel(): void
    {
        // Testing nested indentation
        $code = <<<'PHP'
<?php
class Bar {
    public function foo() {}
}
PHP;
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        ($this->bodyExpander)(
            $tokens,
            $index,
        );

        $expected = <<<'PHP'
<?php
class Bar {
    public function foo() {
    }
}
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
