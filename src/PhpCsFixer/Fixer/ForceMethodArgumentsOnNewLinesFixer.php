<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Fixer;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use RuntimeException;
use SplFileInfo;

class ForceMethodArgumentsOnNewLinesFixer implements FixerInterface
{
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Forces method arguments to be split into multiple lines with trailing commas.',
            [new CodeSample("<?php\nfunction foo(\$a, \$b) {}")],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_FUNCTION);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        // Iterate backwards
        for ($index = $tokens->count() - 1; $index >= 0; $index--) {
            if (!$tokens[$index]->isGivenKind(T_FUNCTION)) {
                continue;
            }

            $openIndex = $tokens->getNextTokenOfKind($index, ['(']);

            if (is_null($openIndex)) {
                throw new RuntimeException('Could not find opening parenthesis for function declaration.');
            }

            $closeIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openIndex);

            // Skip empty functions
            if ($tokens->getNextMeaningfulToken($openIndex) === $closeIndex) {
                continue;
            }

            // 1. Force Trailing Comma (Unconditional)
            $lastArgIndex = $tokens->getPrevMeaningfulToken($closeIndex);

            if ($lastArgIndex === null) {
                throw new RuntimeException('Could not find the last argument token.');
            }

            if (!$tokens[$lastArgIndex]->equals(',')) {
                $tokens->insertAt($lastArgIndex + 1, new Token(','));
                $closeIndex++;
            }

            // 2. Force Newline BEFORE ')'
            // Check previous token. If it's whitespace, REPLACE it. If not, INSERT.
            $prevIndex = $closeIndex - 1;
            $prevToken = $tokens[$prevIndex];

            if ($prevToken->isWhitespace()) {
                if (!str_contains($prevToken->getContent(), "\n")) {
                    $tokens[$prevIndex] = new Token([T_WHITESPACE, "\n"]);
                }
            } else {
                $tokens->insertAt($closeIndex, new Token([T_WHITESPACE, "\n"]));
                $closeIndex++;
            }

            // 3. Force Newlines After Every Comma
            $nestingLevel = 0;
            for ($i = $closeIndex - 1; $i > $openIndex; $i--) {
                $token = $tokens[$i];
                $content = $token->getContent();

                if ($content === ')' || $content === ']' || $content === '}') {
                    $nestingLevel++;
                } elseif ($content === '(' || $content === '[' || $content === '{') {
                    $nestingLevel--;
                }

                if ($nestingLevel === 0 && $content === ',') {
                    $nextIndex = $i + 1;
                    $nextToken = $tokens[$nextIndex];

                    // ABSORB SPACE: If next token is a space, overwrite it with \n
                    if ($nextToken->isWhitespace()) {
                        if (!str_contains($nextToken->getContent(), "\n")) {
                            $tokens[$nextIndex] = new Token([T_WHITESPACE, "\n"]);
                        }
                    } else {
                        // Otherwise (e.g. tightly packed "$a,$b"), insert strictly
                        $tokens->insertAt($nextIndex, new Token([T_WHITESPACE, "\n"]));
                    }
                }
            }

            // 4. Force Newline AFTER '('
            $firstIndex = $openIndex + 1;
            $firstToken = $tokens[$firstIndex];

            if ($firstToken->isWhitespace()) {
                if (!str_contains($firstToken->getContent(), "\n")) {
                    $tokens[$firstIndex] = new Token([T_WHITESPACE, "\n"]);
                }
            } else {
                $tokens->insertAt($firstIndex, new Token([T_WHITESPACE, "\n"]));
            }
        }
    }

    public function getName(): string
    {
        return 'App/force_method_arguments_multiline';
    }

    public function getPriority(): int
    {
        // Run BEFORE method_argument_space (0) to set up the newlines
        return 10;
    }

    public function supports(
        SplFileInfo $file,
    ): bool {
        return true;
    }
}
