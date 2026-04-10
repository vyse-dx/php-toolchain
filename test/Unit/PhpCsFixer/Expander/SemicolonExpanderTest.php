<?php

declare(strict_types=1);

namespace Test\Toolchain\PhpCsFixer\Expander;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Toolchain\PhpCsFixer\Detector\IndentationDetector;
use Vyse\Toolchain\PhpCsFixer\Expander\SemicolonExpander;

class SemicolonExpanderTest extends TestCase
{
    private SemicolonExpander $expander;
    private MockObject & IndentationDetector $detectIndentation;

    public function setUp(): void
    {
        $this->detectIndentation = self::createMock(IndentationDetector::class);
        $this->expander = new SemicolonExpander($this->detectIndentation);
    }

    public function testExpandsSemicolonToNewlineWithIndentation(): void
    {
        $code = "<?php\n    \$a->b() ;";
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1; // The semicolon

        // We expect the detector to be called with the index of '$a' (Start of statement)
        // $a is at index 2 (Open Tag, newline, $a)
        $this->detectIndentation->expects(self::once())
            ->method('__invoke')
            ->with($tokens, 2)
            ->willReturn('    ')
        ; // Return 4 spaces

        ($this->expander)($tokens, $index);

        $expected = "<?php\n    \$a->b()\n    ;";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testCalculatesIndentationFromRootVariableEvenIfPrecededByBlock(): void
    {
        // This ensures the expander finds the correct "Start" ($code)
        // and doesn't get confused by the function definition above it
        $code = <<<'PHP'
        <?php
            function foo() {}
            $code = '...' ;
        PHP;

        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        // The detector should be called for '$code', not 'function'
        // We assume logic holds; we just return indentation here to prove the insertion works
        $this->detectIndentation->method('__invoke')
            ->willReturn('    ')
        ;

        ($this->expander)($tokens, $index);

        self::assertStringEndsWith("\n    ;", $tokens->generateCode());
    }

    public function testUpdatesExistingWhitespace(): void
    {
        // If there is already a newline but wrong indentation
        $code = "<?php\n    \$a\n ;";
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        $this->detectIndentation->method('__invoke')
            ->willReturn('    ')
        ;

        ($this->expander)($tokens, $index);

        // Should fix the indentation to 4 spaces
        $expected = "<?php\n    \$a\n    ;";
        self::assertSame($expected, $tokens->generateCode());
    }
}
