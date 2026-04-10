<?php

declare(strict_types=1);

namespace App\Toolchain\Test\PhpCsFixer\Checker;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vyse\Toolchain\PhpCsFixer\Checker\BodyContentChecker;

class BodyContentCheckerTest extends TestCase
{
    private BodyContentChecker $bodyContentChecker;

    public function setUp(): void
    {
        $this->bodyContentChecker = new BodyContentChecker;
    }

    public function testReturnsFalseWhenBodyIsEmpty(): void
    {
        $code = '<?php function foo() {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->bodyContentChecker)(
            $tokens,
            $index,
        );

        self::assertFalse($result);
    }

    public function testReturnsFalseWhenBodyContainsOnlyWhitespace(): void
    {
        $code = '<?php function foo() {  }';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->bodyContentChecker)(
            $tokens,
            $index,
        );

        self::assertFalse($result);
    }

    public function testReturnsFalseWhenBodyContainsOnlyComments(): void
    {
        $code = '<?php function foo() { /* comment */ }';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->bodyContentChecker)(
            $tokens,
            $index,
        );

        self::assertFalse($result);
    }

    public function testReturnsTrueWhenBodyHasContent(): void
    {
        $code = '<?php function foo() { $a = 1; }';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->bodyContentChecker)(
            $tokens,
            $index,
        );

        self::assertTrue($result);
    }

    public function testReturnsTrueForAbstractMethods(): void
    {
        $code = '<?php interface Foo { public function bar(); }';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->bodyContentChecker)(
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
