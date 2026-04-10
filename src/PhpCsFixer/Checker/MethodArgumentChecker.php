<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Checker;

use PhpCsFixer\Tokenizer\Tokens;

readonly class MethodArgumentChecker
{
    public function __invoke(
        Tokens $tokens,
        int $index,
    ): bool {
        $openParenthesis = $tokens->getNextTokenOfKind($index, ['(']);

        if ($openParenthesis === null) {
            return false;
        }

        $closeParenthesis = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenthesis);

        for ($i = $openParenthesis + 1; $i < $closeParenthesis; ++$i) {
            if (!$tokens[$i]->isWhitespace() && !$tokens[$i]->isComment()) {
                return true;
            }
        }

        return false;
    }
}
