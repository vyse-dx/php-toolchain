<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Checker;

use PhpCsFixer\Tokenizer\Tokens;

readonly class BodyContentChecker
{
    public function __invoke(
        Tokens $tokens,
        int $index,
    ): bool {
        $openBrace = $tokens->getNextTokenOfKind($index, ['{', ';']);

        if ($openBrace === null || $tokens[$openBrace]->equals(';')) {
            return true;
        }

        $closeBrace = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openBrace);

        for ($i = $openBrace + 1; $i < $closeBrace; ++$i) {
            if (!$tokens[$i]->isWhitespace() && !$tokens[$i]->isComment()) {
                return true;
            }
        }

        return false;
    }
}
