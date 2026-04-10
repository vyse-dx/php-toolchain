<?php

declare(strict_types=1);

namespace Test\Toolchain\PhpCsFixer\Checker;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use Vyse\Toolchain\PhpCsFixer\Checker\MultilineStatementChecker;

class MultilineStatementCheckerTest extends TestCase
{
    private MultilineStatementChecker $checker;

    public function setUp(): void
    {
        $this->checker = new MultilineStatementChecker;
    }

    public function testReturnsFalseForSingleLineStatements(): void
    {
        $code = '<?php $a = 1;';
        $tokens = Tokens::fromCode($code);

        $index = $tokens->getNextTokenOfKind(0, [';']);

        self::assertIsInt($index);
        self::assertFalse(($this->checker)($tokens, $index));
    }

    public function testReturnsTrueForChainedMethodCallsOnNewLines(): void
    {
        $code = "<?php\n\$a->b()\n    ->c();";
        $tokens = Tokens::fromCode($code);

        $index = $tokens->getNextTokenOfKind(0, [';']);

        self::assertIsInt($index);
        self::assertTrue(($this->checker)($tokens, $index));
    }

    public function testReturnsFalseForMultilineArraysAssignment(): void
    {
        $code = "<?php\n\$a = [\n    1,\n    2,\n];";
        $tokens = Tokens::fromCode($code);

        // Only one semicolon, safe to find from start
        $index = $tokens->getNextTokenOfKind(0, [';']);

        self::assertIsInt($index);
        self::assertFalse(($this->checker)($tokens, $index));
    }

    public function testReturnsFalseForClosuresDefinedOnMultipleLines(): void
    {
        $code = "<?php\n\$fn = function () {\n    return true;\n};";
        $tokens = Tokens::fromCode($code);

        // We want the LAST semicolon (the assignment), not the one inside the closure
        // Since it's the last token, we grab it directly.
        $index = $tokens->count() - 1;

        self::assertSame(';', $tokens[$index]->getContent());
        self::assertFalse(($this->checker)($tokens, $index));
    }

    public function testReturnsFalseForAnonymousClasses(): void
    {
        $code = "<?php\n\$obj = new class {\n    public function foo() {}\n};";
        $tokens = Tokens::fromCode($code);

        // We want the LAST semicolon.
        $index = $tokens->count() - 1;

        self::assertSame(';', $tokens[$index]->getContent());
        self::assertFalse(($this->checker)($tokens, $index));
    }

    public function testReturnsTrueForSplitOperators(): void
    {
        $code = "<?php\n\$a = \$b\n    || \$c;";
        $tokens = Tokens::fromCode($code);

        $index = $tokens->getNextTokenOfKind(0, [';']);

        self::assertIsInt($index);
        self::assertTrue(($this->checker)($tokens, $index));
    }

    public function testReturnsFalseForDeclareStrictTypes(): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);";
        $tokens = Tokens::fromCode($code);

        $index = $tokens->getNextTokenOfKind(0, [';']);

        self::assertIsInt($index);
        self::assertFalse(($this->checker)($tokens, $index));
    }

    public function testReturnsFalseForMultilineStrings(): void
    {
        $code = "<?php\n\$sql = 'SELECT *\nFROM table';";
        $tokens = Tokens::fromCode($code);

        $index = $tokens->getNextTokenOfKind(0, [';']);

        self::assertIsInt($index);
        self::assertFalse(($this->checker)($tokens, $index));
    }

    public function testCorrectlyIdentifiesBoundariesWithPrecedingBlocks(): void
    {
        $code = <<<'PHP'
        <?php
        function foo() {
            foreach ($items as $item) {
            }
        
            throw new Exception("Error");
        }
        PHP;

        $tokens = Tokens::fromCode($code);

        // The last token inside the function is the semicolon for the throw.
        // It's technically not the absolute last token of the file (the closing brace } is),
        // but getPrevTokenOfKind searches backwards from START-1.
        // So passing count() works best.
        $index = $tokens->getPrevTokenOfKind($tokens->count(), [';']);

        self::assertIsInt($index);
        self::assertSame(';', $tokens[$index]->getContent());
        self::assertFalse(($this->checker)($tokens, $index));
    }
}
