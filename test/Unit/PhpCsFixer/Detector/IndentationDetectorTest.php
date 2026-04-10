<?php

declare(strict_types=1);

namespace App\Toolchain\Test\PhpCsFixer\Detector;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vyse\Toolchain\PhpCsFixer\Detector\IndentationDetector;

class IndentationDetectorTest extends TestCase
{
    private IndentationDetector $indentationDetector;

    public function setUp(): void
    {
        $this->indentationDetector = new IndentationDetector;
    }

    public function testDetectsNoIndentation(): void
    {
        $code = '<?php
function foo() {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->indentationDetector)(
            $tokens,
            $index,
        );

        self::assertSame(
            '',
            $result,
        );
    }

    public function testDetectsFourSpacesIndentation(): void
    {
        $code = '<?php
    function foo() {}';
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->indentationDetector)(
            $tokens,
            $index,
        );

        self::assertSame(
            '    ',
            $result,
        );
    }

    public function testDetectsTabIndentation(): void
    {
        $code = "<?php\n\tfunction foo() {}";
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->indentationDetector)(
            $tokens,
            $index,
        );

        self::assertSame(
            "\t",
            $result,
        );
    }

    public function testDetectsIndentationInsideClass(): void
    {
        $code = <<<'PHP'
<?php
class Bar
{
    public function foo() {}
}
PHP;
        $tokens = Tokens::fromCode($code);
        $index = $this->findFunctionIndex($tokens);

        $result = ($this->indentationDetector)(
            $tokens,
            $index,
        );

        self::assertSame(
            '    ',
            $result,
        );
    }

    private function findFunctionIndex(
        Tokens $tokens,
    ): int {
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_FUNCTION)) {
                return $index;
            }
        }

        throw new RuntimeException('No function token found in test code.');
    }
}
