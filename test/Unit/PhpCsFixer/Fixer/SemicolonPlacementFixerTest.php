<?php

declare(strict_types=1);

namespace Test\Toolchain\PhpCsFixer\Fixer;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Vyse\Toolchain\PhpCsFixer\Checker\MultilineStatementChecker;
use Vyse\Toolchain\PhpCsFixer\Collapser\SemicolonCollapser;
use Vyse\Toolchain\PhpCsFixer\Expander\SemicolonExpander;
use Vyse\Toolchain\PhpCsFixer\Fixer\SemicolonPlacementFixer;

class SemicolonPlacementFixerTest extends TestCase
{
    private SemicolonPlacementFixer $fixer;
    private MockObject & MultilineStatementChecker $checker;
    private MockObject & SemicolonExpander $expander;
    private MockObject & SemicolonCollapser $collapser;

    public function setUp(): void
    {
        $this->checker = self::createMock(MultilineStatementChecker::class);
        $this->expander = self::createMock(SemicolonExpander::class);
        $this->collapser = self::createMock(SemicolonCollapser::class);

        $this->fixer = new SemicolonPlacementFixer(
            $this->checker,
            $this->expander,
            $this->collapser,
        );
    }

    public function testIsCandidateChecksForSemicolons(): void
    {
        $tokens = Tokens::fromCode('<?php $a = 1;');
        self::assertTrue($this->fixer->isCandidate($tokens));

        // Use a comment to ensure valid syntax but no semicolon
        $tokensEmpty = Tokens::fromCode('<?php // No semicolon here');
        self::assertFalse($this->fixer->isCandidate($tokensEmpty));
    }

    public function testDelegatesToExpanderWhenCheckerReturnsTrue(): void
    {
        $tokens = Tokens::fromCode('<?php $a->b();');
        $file = new SplFileInfo('test.php');

        // Find the specific index of the semicolon dynamically
        $semicolonIndex = $tokens->getNextTokenOfKind(0, [';']);

        $this->checker->expects(self::once())
            ->method('__invoke')
            ->with($tokens, $semicolonIndex)
            ->willReturn(true)
        ;

        $this->expander->expects(self::once())
            ->method('__invoke')
            ->with($tokens, $semicolonIndex)
        ;

        $this->collapser->expects(self::never())
            ->method('__invoke')
        ;

        $this->fixer->fix($file, $tokens);
    }

    public function testDelegatesToCollapserWhenCheckerReturnsFalse(): void
    {
        $tokens = Tokens::fromCode('<?php $a = 1;');
        $file = new SplFileInfo('test.php');

        $semicolonIndex = $tokens->getNextTokenOfKind(0, [';']);

        $this->checker->expects(self::once())
            ->method('__invoke')
            ->with($tokens, $semicolonIndex)
            ->willReturn(false)
        ;

        $this->collapser->expects(self::once())
            ->method('__invoke')
            ->with($tokens, $semicolonIndex)
        ;

        $this->expander->expects(self::never())
            ->method('__invoke')
        ;

        $this->fixer->fix($file, $tokens);
    }
}
