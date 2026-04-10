<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

class MockObjectIntersectionOrderFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'App/mock_object_intersection_order';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'MockObject must always be the first type in an intersection type declaration.',
            [
                new CodeSample("<?php\nclass Foo { public function bar(RouterInterface&MockObject \$mock): void {} }"),
            ],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_STRING);
    }

    public function getPriority(): int
    {
        // Run before types_spaces (which runs at Priority 0) so the spacing rule
        // can clean up anything we shift around if necessary.
        return 10;
    }

    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        // Iterate backwards to safely modify token boundaries without throwing off the main loop index
        for ($index = $tokens->count() - 1; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (!$token->isGivenKind(T_STRING) || $token->getContent() !== 'MockObject') {
                continue;
            }

            $this->bubbleMockObjectToFront($tokens, $index);
        }
    }

    private function bubbleMockObjectToFront(
        Tokens $tokens,
        int $mockObjectIndex,
    ): void {
        $currentIndex = $mockObjectIndex;

        while (true) {
            // 1. Find the full boundaries of the Right Type (MockObject + potential namespaces) FIRST
            $rightTypeStart = $currentIndex;
            while (true) {
                $prevOfRight = $tokens->getPrevMeaningfulToken($rightTypeStart);
                if ($prevOfRight !== null && $tokens[$prevOfRight]->isGivenKind([T_STRING, T_NS_SEPARATOR])) {
                    $rightTypeStart = $prevOfRight;
                } else {
                    break;
                }
            }
            $rightTypeEnd = $currentIndex; // The T_STRING 'MockObject'

            // 2. Now check what precedes the START of the Right Type
            $prevIndex = $tokens->getPrevMeaningfulToken($rightTypeStart);
            if ($prevIndex === null) {
                break;
            }

            $prevToken = $tokens[$prevIndex];

            if (!$prevToken->isGivenKind(CT::T_TYPE_INTERSECTION) && $prevToken->getContent() !== '&') {
                break; // Not an intersection, stop bubbling
            }

            // 3. Find the boundaries of the Left Type
            $leftTypeEnd = $tokens->getPrevMeaningfulToken($prevIndex);
            if ($leftTypeEnd === null) {
                break;
            }

            $leftTypeStart = $leftTypeEnd;
            while (true) {
                $prevOfLeft = $tokens->getPrevMeaningfulToken($leftTypeStart);
                if ($prevOfLeft !== null && $tokens[$prevOfLeft]->isGivenKind([T_STRING, T_NS_SEPARATOR])) {
                    $leftTypeStart = $prevOfLeft;
                } else {
                    break;
                }
            }

            // Build the swapped token sequence
            $newTokens = [];

            // Put the Right Type first
            for ($i = $rightTypeStart; $i <= $rightTypeEnd; $i++) {
                $newTokens[] = clone $tokens[$i];
            }
            // Keep the middle exactly the same (whitespace and intersection operator)
            for ($i = $leftTypeEnd + 1; $i < $rightTypeStart; $i++) {
                $newTokens[] = clone $tokens[$i];
            }
            // Put the Left Type last
            for ($i = $leftTypeStart; $i <= $leftTypeEnd; $i++) {
                $newTokens[] = clone $tokens[$i];
            }

            // Overwrite the entire sequence
            $tokens->overrideRange($leftTypeStart, $rightTypeEnd, $newTokens);

            // Update currentIndex to continue bubbling leftwards if necessary
            // (e.g., A & B & MockObject -> MockObject & A & B)
            $rightTypeLength = $rightTypeEnd - $rightTypeStart + 1;
            $currentIndex = $leftTypeStart + $rightTypeLength - 1;
        }
    }
}
