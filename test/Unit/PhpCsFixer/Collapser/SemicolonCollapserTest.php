<?php

declare(strict_types=1);

namespace Test\Toolchain\PhpCsFixer\Collapser;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use Vyse\Toolchain\PhpCsFixer\Collapser\SemicolonCollapser;

class SemicolonCollapserTest extends TestCase
{
    private SemicolonCollapser $collapser;

    public function setUp(): void
    {
        $this->collapser = new SemicolonCollapser;
    }

    public function testCollapsesWhitespacePrecedingSemicolon(): void
    {
        $code = '<?php $a = 1   ;';
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        ($this->collapser)($tokens, $index);

        self::assertSame('<?php $a = 1;', $tokens->generateCode());
    }

    public function testCollapsesNewlinePrecedingSemicolon(): void
    {
        $code = "<?php \$a = 1\n;";
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        ($this->collapser)($tokens, $index);

        self::assertSame('<?php $a = 1;', $tokens->generateCode());
    }

    public function testDoesNotCollapseIfPrecededBySingleLineComment(): void
    {
        // If we collapse here, the semicolon becomes part of the comment
        $code = "<?php \$a = 1; // comment\n;";
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        ($this->collapser)($tokens, $index);

        // Should remain unchanged
        self::assertSame("<?php \$a = 1; // comment\n;", $tokens->generateCode());
    }

    public function testDoesNotCollapseIfPrecededByHashComment(): void
    {
        $code = "<?php \$a = 1; # comment\n;";
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        ($this->collapser)($tokens, $index);

        self::assertSame("<?php \$a = 1; # comment\n;", $tokens->generateCode());
    }

    public function testCollapsesIfPrecededByMultiLineComment(): void
    {
        // /* */ comments define their own end, so safe to collapse against
        $code = "<?php \$a = 1 /* comment */ ;";
        $tokens = Tokens::fromCode($code);
        $index = $tokens->count() - 1;

        ($this->collapser)($tokens, $index);

        // Note: The collapser removes whitespace between the previous token (comment) and semicolon
        self::assertSame('<?php $a = 1 /* comment */;', $tokens->generateCode());
    }
}
