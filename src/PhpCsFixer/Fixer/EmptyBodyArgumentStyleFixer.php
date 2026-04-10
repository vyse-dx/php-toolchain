<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Vyse\Toolchain\PhpCsFixer\Checker;
use Vyse\Toolchain\PhpCsFixer\Collapser;
use Vyse\Toolchain\PhpCsFixer\Expander;

class EmptyBodyArgumentStyleFixer extends AbstractFixer
{
    public function __construct(
        private Checker\MethodArgumentChecker $checkArguments = new Checker\MethodArgumentChecker,
        private Checker\BodyContentChecker $checkBodyContent = new Checker\BodyContentChecker,
        private Expander\BodyExpander $expandBody = new Expander\BodyExpander,
        private Collapser\BodyCollapser $collapseBody = new Collapser\BodyCollapser,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'App/empty_body_argument_style';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Empty method bodies must be single-line if no arguments, but multi-line if arguments exist.',
            [
                new CodeSample("<?php\nfunction foo() {}\nfunction bar(\$a) {}"),
            ],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_FUNCTION);
    }

    public function getPriority(): int
    {
        return -10;
    }

    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        for ($index = $tokens->count() - 1; $index >= 0; $index--) {
            if (!$tokens[$index]->isGivenKind(T_FUNCTION)) {
                continue;
            }

            if (($this->checkBodyContent)($tokens, $index)) {
                continue;
            }

            if (($this->checkArguments)($tokens, $index)) {
                ($this->expandBody)($tokens, $index);

                continue;
            }

            ($this->collapseBody)($tokens, $index);
        }
    }
}
