<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Expander;

use PhpCsFixer\Tokenizer\Tokens;
use Vyse\Toolchain\PhpCsFixer\Detector\IndentationDetector;

readonly class BodyExpander
{
    public function __construct(
        private IndentationDetector $detectIndentation = new IndentationDetector,
    ) {
    }

    public function __invoke(
        Tokens $tokens,
        int $index,
    ): void {
        $openBrace = $tokens->getNextTokenOfKind($index, ['{']);

        if ($openBrace === null) {
            return;
        }

        $closeBrace = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openBrace);

        if ($this->isMultiLine($tokens, $openBrace, $closeBrace)) {
            return;
        }

        $indent = ($this->detectIndentation)($tokens, $index);

        $tokens->ensureWhitespaceAtIndex(
            $closeBrace - 1,
            1,
            "\n" . $indent,
        );
    }

    private function isMultiLine(
        Tokens $tokens,
        int $start,
        int $end,
    ): bool {
        for ($i = $start; $i <= $end; ++$i) {
            if (str_contains($tokens[$i]->getContent(), "\n")) {
                return true;
            }
        }

        return false;
    }
}
