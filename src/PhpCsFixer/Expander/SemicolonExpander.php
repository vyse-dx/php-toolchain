<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Expander;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Vyse\Toolchain\PhpCsFixer\Detector\IndentationDetector;

readonly class SemicolonExpander
{
    public function __construct(
        private IndentationDetector $detectIndentation = new IndentationDetector,
    ) {
    }

    public function __invoke(
        Tokens $tokens,
        int $index,
    ): void {
        $stmtStart = $this->findStatementStart($tokens, $index);

        if ($stmtStart === null) {
            return;
        }

        $indent = ($this->detectIndentation)($tokens, $stmtStart);
        $newContent = "\n" . $indent;

        $prevIndex = $index - 1;
        $prevToken = $tokens[$prevIndex];

        if ($prevToken->isWhitespace()) {
            if ($prevToken->getContent() !== $newContent) {
                $tokens[$prevIndex] = new Token([T_WHITESPACE, $newContent]);
            }
        } else {
            $tokens->insertAt($index, new Token([T_WHITESPACE, $newContent]));
        }
    }

    private function findStatementStart(
        Tokens $tokens,
        int $semicolonIndex,
    ): ?int {
        $current = $tokens->getPrevMeaningfulToken($semicolonIndex);

        while ($current !== null) {
            $token = $tokens[$current];

            if ($token->equals(';') ||
                $token->equals('{') ||
                $token->isGivenKind([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])
            ) {
                return $tokens->getNextMeaningfulToken($current);
            }

            if ($token->equals('}')) {
                $blockStart = $tokens->findBlockStart(Tokens::BLOCK_TYPE_CURLY_BRACE, $current);

                if ($this->isControlStructureBlock($tokens, $blockStart)) {
                    return $tokens->getNextMeaningfulToken($current);
                }

                $current = $tokens->getPrevMeaningfulToken($blockStart);

                continue;
            }

            $content = $token->getContent();
            if ($content === ')' || $content === ']') {
                $openChar = $content === ')' ? '(' : '[';
                $localDepth = 1;
                $current--;
                while ($current >= 0) {
                    $c = $tokens[$current]->getContent();
                    if ($c === $content) {
                        $localDepth++;
                    } elseif ($c === $openChar) {
                        $localDepth--;
                    }
                    if ($localDepth === 0) {
                        break;
                    }
                    $current--;
                }
                $current = $tokens->getPrevMeaningfulToken($current);

                continue;
            }

            $current = $tokens->getPrevMeaningfulToken($current);
        }

        return $tokens->getNextMeaningfulToken(-1);
    }

    private function isControlStructureBlock(
        Tokens $tokens,
        int $openBraceIndex,
    ): bool {
        $prevIndex = $tokens->getPrevMeaningfulToken($openBraceIndex);
        if ($prevIndex === null) {
            return false;
        }

        $prevToken = $tokens[$prevIndex];

        if ($prevToken->isGivenKind([T_ELSE, T_TRY, T_FINALLY, T_DO])) {
            return true;
        }

        if ($prevToken->equals(')')) {
            $openParenIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $prevIndex);
            $beforeParenIndex = $tokens->getPrevMeaningfulToken($openParenIndex);

            if ($beforeParenIndex !== null) {
                $beforeParenToken = $tokens[$beforeParenIndex];

                if ($beforeParenToken->isGivenKind([
                    T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_CATCH
                ])) {
                    return true;
                }

                if ($beforeParenToken->isGivenKind(T_STRING)) {
                    $maybeFunction = $tokens->getPrevMeaningfulToken($beforeParenIndex);
                    if ($maybeFunction !== null && $tokens[$maybeFunction]->isGivenKind(T_FUNCTION)) {
                        return true;
                    }
                }
            }
        }

        if ($prevToken->isGivenKind([T_STRING, T_IMPLEMENTS, T_EXTENDS])) {
            $currentIndex = $prevIndex;
            while ($currentIndex !== null && !$tokens[$currentIndex]->isGivenKind([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                $currentIndex = $tokens->getPrevMeaningfulToken($currentIndex);
            }
            if ($currentIndex !== null) {
                $beforeKeyword = $tokens->getPrevMeaningfulToken($currentIndex);
                if ($beforeKeyword !== null && $tokens[$beforeKeyword]->isGivenKind(T_NEW)) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }
}
