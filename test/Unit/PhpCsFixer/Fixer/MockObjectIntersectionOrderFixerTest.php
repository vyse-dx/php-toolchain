<?php

declare(strict_types=1);

namespace Test\Unit\Toolchain\PhpCsFixer\Fixer;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Vyse\Toolchain\PhpCsFixer\Fixer\MockObjectIntersectionOrderFixer;

final class MockObjectIntersectionOrderFixerTest extends TestCase
{
    private MockObjectIntersectionOrderFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixer = new MockObjectIntersectionOrderFixer();
    }

    #[DataProvider('provideFixCases')]
    public function testFix(
        string $expected,
        string $input,
    ): void {
        $tokens = Tokens::fromCode($input);
        $file = new SplFileInfo(__FILE__);

        $this->fixer->fix($file, $tokens);

        self::assertSame($expected, $tokens->generateCode());
    }

    public function testDoesNotModifyCodeWithoutMockObjectIntersection(): void
    {
        $code = "<?php\nclass Foo { public function bar(MockObject \$mock, RouterInterface \$router): void {} }";
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $this->fixer->fix($file, $tokens);

        self::assertSame($code, $tokens->generateCode());
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function provideFixCases(): iterable
    {
        yield 'simple swap no spaces' => [
            "<?php\nfunction test(MockObject&RouterInterface \$mock) {}",
            "<?php\nfunction test(RouterInterface&MockObject \$mock) {}",
        ];

        yield 'simple swap with spaces' => [
            "<?php\nfunction test(MockObject & RouterInterface \$mock) {}",
            "<?php\nfunction test(RouterInterface & MockObject \$mock) {}",
        ];

        yield 'bubbles past multiple types' => [
            "<?php\nfunction test(MockObject & ClassA & ClassB \$mock) {}",
            "<?php\nfunction test(ClassA & ClassB & MockObject \$mock) {}",
        ];

        yield 'handles left side FQCN' => [
            "<?php\nfunction test(MockObject & \\App\\Router\\RouterInterface \$mock) {}",
            "<?php\nfunction test(\\App\\Router\\RouterInterface & MockObject \$mock) {}",
        ];

        yield 'handles namespaced MockObject on right side' => [
            "<?php\nfunction test(\\PHPUnit\\Framework\\MockObject\\MockObject & RouterInterface \$mock) {}",
            "<?php\nfunction test(RouterInterface & \\PHPUnit\\Framework\\MockObject\\MockObject \$mock) {}",
        ];

        yield 'preserves multi-line formatting' => [
            "<?php\nfunction test(\n    MockObject\n    &\n    RouterInterface \$mock\n) {}",
            "<?php\nfunction test(\n    RouterInterface\n    &\n    MockObject \$mock\n) {}",
        ];
    }
}
