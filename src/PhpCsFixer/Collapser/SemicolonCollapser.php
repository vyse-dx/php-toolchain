<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Collapser;

use PhpCsFixer\Tokenizer\Tokens;

readonly class SemicolonCollapser
{
    public function __invoke(
        Tokens $tokens,
        int $index,
    ): void {
        $prevIndex = $index - 1;

        // We want to snap the semicolon to the previous token.
        // We scan backwards from the semicolon until we hit a non-whitespace token.
        // We clear everything in between.

        // Safety check: Don't collapse if the previous token is a comment (Single line comments require a newline)
        // But $prevIndex is strictly the index - 1.

        if (!$tokens[$prevIndex]->isWhitespace()) {
            return;
        }

        // Find the extent of the whitespace
        $whitespaceStart = $prevIndex;
        while ($tokens[$whitespaceStart - 1]->isWhitespace()) {
            $whitespaceStart--;
        }

        // Check if the thing BEFORE the whitespace is a comment.
        // If it is "// comment", we CANNOT collapse, as the semicolon would become part of the comment.
        $tokenBeforeWhitespace = $tokens[$whitespaceStart - 1];
        if ($tokenBeforeWhitespace->isComment() && !str_starts_with($tokenBeforeWhitespace->getContent(), '/*')) {
            // It's likely a single line comment (// or #). We must preserve the newline.
            // Ideally, we ensure there is strictly ONE newline, but for now, we leave it alone
            // to avoid syntax errors.
            return;
        }

        $tokens->clearRange($whitespaceStart, $prevIndex);
    }
}
