<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Vyse\Toolchain\PhpCsFixer\Checker\MultilineStatementChecker;
use Vyse\Toolchain\PhpCsFixer\Collapser\SemicolonCollapser;
use Vyse\Toolchain\PhpCsFixer\Expander\SemicolonExpander;

class SemicolonPlacementFixer extends AbstractFixer
{
    public function __construct(
        private MultilineStatementChecker $checkMultiline = new MultilineStatementChecker,
        private SemicolonExpander $expandSemicolon = new SemicolonExpander,
        private SemicolonCollapser $collapseSemicolon = new SemicolonCollapser,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'App/semicolon_placement';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Semicolons must be on their own line if the statement is multiline (chained/split), otherwise on the same line.',
            [
                new CodeSample("<?php\n\$a->b()\n    ->c();\n\$x = 1\n;"),
            ],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(';');
    }

    public function getPriority(): int
    {
        // Run after indentation and argument spacing fixes
        return -25;
    }

    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        for ($index = $tokens->count() - 1; $index >= 0; $index--) {
            if (!$tokens[$index]->equals(';')) {
                continue;
            }

            if (($this->checkMultiline)($tokens, $index)) {
                ($this->expandSemicolon)($tokens, $index);
            } else {
                ($this->collapseSemicolon)($tokens, $index);
            }
        }
    }
}
