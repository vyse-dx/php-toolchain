<?php

declare(strict_types=1);

namespace App\Toolchain\Test\PhpCsFixer\Checker;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vyse\Toolchain\PhpCsFixer\Checker\MethodArgumentChecker;

class MethodArgumentCheckerTest extends TestCase
{
    private MethodArgumentChecker $methodArgumentChecker;

    public function setUp(): void
    {
        $this->methodArgumentChecker = new MethodArgumentChecker;
    }

    public function testReturnsFalseWhenNoArgumentsProvided(): void
    {
        $code = '<?php function foo() {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->methodArgumentChecker)(
            $tokens,
            $index,
        );

        self::assertFalse($result);
    }

    public function testReturnsFalseWhenOnlyWhitespaceIsProvided(): void
    {
        $code = '<?php function foo(  ) {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->methodArgumentChecker)(
            $tokens,
            $index,
        );

        self::assertFalse($result);
    }

    public function testReturnsFalseWhenOnlyCommentsAreProvided(): void
    {
        $code = '<?php function foo( /* comment */ ) {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->methodArgumentChecker)(
            $tokens,
            $index,
        );

        self::assertFalse($result);
    }

    public function testReturnsTrueWhenArgumentsAreProvided(): void
    {
        $code = '<?php function foo($bar) {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->methodArgumentChecker)(
            $tokens,
            $index,
        );

        self::assertTrue($result);
    }

    public function testReturnsTrueWhenArgumentsAreProvidedAcrossMultipleLines(): void
    {
        $code = '<?php function foo(
            string $bar,
        ) {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->methodArgumentChecker)(
            $tokens,
            $index,
        );

        self::assertTrue($result);
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
