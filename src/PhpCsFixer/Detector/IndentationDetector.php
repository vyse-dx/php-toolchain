<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Detector;

use PhpCsFixer\Tokenizer\Tokens;

readonly class IndentationDetector
{
    public function __invoke(
        Tokens $tokens,
        int $index,
    ): string {
        $newlineTokenIndex = -1;

        for ($i = $index - 1; $i >= 0; $i--) {
            if (str_contains($tokens[$i]->getContent(), "\n")) {
                $newlineTokenIndex = $i;

                break;
            }
        }

        if ($newlineTokenIndex === -1) {
            return '';
        }

        $content = $tokens[$newlineTokenIndex]->getContent();
        $afterNewline = substr(
            $content,
            (int) strrpos($content, "\n") + 1,
        );

        preg_match('/^[ \t]*/', $afterNewline, $matches);
        $indent = $matches[0] ?? '';

        if (strlen($indent) < strlen($afterNewline)) {
            return $indent;
        }

        for ($i = $newlineTokenIndex + 1; $i < $index; $i++) {
            $token = $tokens[$i];

            if (!$token->isWhitespace()) {
                break;
            }

            $indent .= $token->getContent();
        }

        return $indent;
    }
}
