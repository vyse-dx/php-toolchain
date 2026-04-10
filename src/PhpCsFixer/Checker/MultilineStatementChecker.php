<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Checker;

use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Tokens;
use Throwable;

readonly class MultilineStatementChecker
{
    public function __invoke(
        Tokens $tokens,
        int $index,
    ): bool {
        if (!$tokens[$index]->equals(';')) {
            return false;
        }

        $prevIndex = $tokens->getPrevMeaningfulToken($index);

        if ($prevIndex !== null && $tokens[$prevIndex]->isGivenKind(T_END_HEREDOC)) {
            return false;
        }

        $statementStartIndex = $this->findStatementStart($tokens, $index);

        if ($statementStartIndex === null) {
            return false;
        }

        // Fast-forward past leading attributes so their newlines don't trigger the multiline rule
        while ($statementStartIndex !== null && $this->isAttributeOpener($tokens, $statementStartIndex)) {
            $localDepth = 1;
            $searchIndex = $statementStartIndex + 1;
            while ($searchIndex < $tokens->count()) {
                $c = $tokens[$searchIndex]->getContent();
                if ($c === '[') {
                    $localDepth++;
                } elseif ($c === ']') {
                    $localDepth--;
                }

                if ($localDepth === 0) {
                    $statementStartIndex = $tokens->getNextMeaningfulToken($searchIndex);

                    break;
                }
                $searchIndex++;
            }
        }

        if ($statementStartIndex === null || $statementStartIndex >= $prevIndex) {
            return false;
        }

        for ($i = $statementStartIndex; $i <= $prevIndex; $i++) {
            $token = $tokens[$i];

            if ($token->isComment()) {
                continue;
            }

            $blockType = null;
            if ($token->equals('(')) {
                $blockType = Tokens::BLOCK_TYPE_PARENTHESIS_BRACE;
            } elseif ($token->equals('{')) {
                $blockType = Tokens::BLOCK_TYPE_CURLY_BRACE;
            } elseif ($token->equals('[') || $token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
                $blockType = Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE;
            }

            if ($blockType !== null) {
                try {
                    $endIndex = $tokens->findBlockEnd($blockType, $i);
                    if ($endIndex <= $prevIndex) {
                        $i = $endIndex;

                        continue;
                    }
                } catch (Throwable $e) {
                }
            }

            if ($token->isWhitespace() && str_contains($token->getContent(), "\n")) {
                return true;
            }
        }

        return false;
    }

    private function findStatementStart(
        Tokens $tokens,
        int $semicolonIndex,
    ): ?int {
        $current = $tokens->getPrevMeaningfulToken($semicolonIndex);

        while ($current !== null) {
            $token = $tokens[$current];

            // 1. Root Level Terminators
            if (
                $token->equals(';') ||
                $token->equals('{') ||
                ($token->equals(':') && $this->isColonStatementBoundary($tokens, $current)) ||
                $token->isGivenKind([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])
            ) {
                return $tokens->getNextMeaningfulToken($current);
            }

            // 2. Curly Braces (Check if it's a control structure or an expression)
            if ($token->equals('}')) {
                $blockStart = $tokens->findBlockStart(Tokens::BLOCK_TYPE_CURLY_BRACE, $current);

                if ($this->isControlStructureBlock($tokens, $blockStart)) {
                    // It's a control structure (e.g. if, while), so this `}` is the end of the PREVIOUS statement.
                    return $tokens->getNextMeaningfulToken($current);
                }

                // It's an expression (e.g. closure, match), jump over it
                $current = $tokens->getPrevMeaningfulToken($blockStart);

                continue;
            }

            // 3. Parentheses, Brackets, and Attributes (Always expressions/declarations, jump over them)
            $content = $token->getContent();
            if ($content === ')' || $content === ']') {
                $openChar = $content === ')' ? '(' : '[';
                $localDepth = 1;
                $current--;
                while ($current >= 0) {
                    $currToken = $tokens[$current];
                    $c = $currToken->getContent();

                    if ($c === $content) {
                        $localDepth++;
                    } elseif ($c === $openChar || ($content === ']' && $this->isAttributeOpener($tokens, $current))) {
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

    private function isColonStatementBoundary(
        Tokens $tokens,
        int $colonIndex,
    ): bool {
        $current = $tokens->getPrevMeaningfulToken($colonIndex);
        $ternaryDepth = 1;
        $bracketDepth = 0;

        while ($current !== null) {
            $token = $tokens[$current];

            // Track block depth to ensure we only evaluate tokens at the same level as the colon
            if ($token->equals(')') || $token->equals(']') || $token->equals('}')) {
                $bracketDepth++;
            } elseif ($token->equals('(') || $token->equals('[') || $token->equals('{')) {
                $bracketDepth--;
            }

            if ($bracketDepth === 0) {
                if ($token->equals(':')) {
                    $ternaryDepth++; // Handle nested ternaries
                } elseif ($token->equals('?')) {
                    $ternaryDepth--;
                    if ($ternaryDepth === 0) {
                        return false; // It's a ternary colon, jump over it!
                    }
                } elseif ($token->isGivenKind([T_CASE, T_DEFAULT])) {
                    return true; // Hit a case/default keyword, it's a boundary
                } elseif ($token->isGivenKind([T_FUNCTION, T_FN])) {
                    return false; // Hit a function keyword, it's a return type colon
                } elseif ($token->equals(';') || $token->isGivenKind([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                    return true; // Hit the previous statement, it's a goto label boundary
                }
            }

            // If we exited the block containing the colon without finding a `?`, it's a label.
            if ($bracketDepth < 0) {
                return true;
            }

            $current = $tokens->getPrevMeaningfulToken($current);
        }

        return true;
    }

    private function isAttributeOpener(
        Tokens $tokens,
        int $index,
    ): bool {
        $token = $tokens[$index];

        return $token->getContent() === '#['
            || (defined('T_ATTRIBUTE') && $token->isGivenKind(T_ATTRIBUTE))
        ;
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

        // e.g. else { ... }, try { ... }
        if ($prevToken->isGivenKind([T_ELSE, T_TRY, T_FINALLY, T_DO])) {
            return true;
        }

        // e.g. if (...) {, while (...) {
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

                // Check for named functions (statement) vs closures (expression)
                if ($beforeParenToken->isGivenKind(T_STRING)) {
                    $maybeFunction = $tokens->getPrevMeaningfulToken($beforeParenIndex);
                    if ($maybeFunction !== null && $tokens[$maybeFunction]->isGivenKind(T_FUNCTION)) {
                        return true;
                    }
                }
            }
        }

        // e.g. class Foo { ... }
        if ($prevToken->isGivenKind([T_STRING, T_IMPLEMENTS, T_EXTENDS])) {
            $currentIndex = $prevIndex;
            while ($currentIndex !== null && !$tokens[$currentIndex]->isGivenKind([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                $currentIndex = $tokens->getPrevMeaningfulToken($currentIndex);
            }
            if ($currentIndex !== null) {
                $beforeKeyword = $tokens->getPrevMeaningfulToken($currentIndex);
                if ($beforeKeyword !== null && $tokens[$beforeKeyword]->isGivenKind(T_NEW)) {
                    return false; // Anonymous class -> expression
                }

                return true; // Named class -> statement
            }
        }

        return false;
    }
}
