<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Collapser;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

readonly class BodyCollapser
{
    public function __invoke(
        Tokens $tokens,
        int $index,
    ): void {
        $openBrace = $tokens->getNextTokenOfKind($index, ['{']);

        if ($openBrace === null) {
            return;
        }

        $closeBrace = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openBrace);

        $tokens->clearRange($openBrace + 1, $closeBrace - 1);

        $prevIndex = $openBrace - 1;

        if ($tokens[$prevIndex]->isWhitespace()) {
            $tokens[$prevIndex] = new Token([T_WHITESPACE, ' ']);

            return;
        }

        if (!$tokens[$prevIndex]->isComment()) {
            $whitespace = new Token([T_WHITESPACE, ' ']);

            $tokens->insertAt($openBrace, $whitespace);
        }
    }
}
