<?php

declare(strict_types=1);

namespace App\Toolchain\Test\PhpCsFixer\Fixer;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Vyse\Toolchain\PhpCsFixer\Fixer\EmptyBodyArgumentStyleFixer;

class EmptyBodyArgumentStyleFixerTest extends TestCase
{
    private EmptyBodyArgumentStyleFixer $fixer;

    public function setUp(): void
    {
        $this->fixer = new EmptyBodyArgumentStyleFixer;
    }

    public function testCollapsesEmptyMethodWithNoArguments(): void
    {
        $input = <<<'PHP'
<?php
class Foo
{
    public function bar() {
    }
}
PHP;

        $expected = <<<'PHP'
<?php
class Foo
{
    public function bar() {}
}
PHP;

        self::assertSame(
            $expected,
            $this->fix($input),
        );
    }

    public function testExpandsEmptyMethodWithArguments(): void
    {
        $input = <<<'PHP'
<?php
class Foo
{
    public function bar($a) {}
}
PHP;

        $expected = <<<'PHP'
<?php
class Foo
{
    public function bar($a) {
    }
}
PHP;

        self::assertSame(
            $expected,
            $this->fix($input),
        );
    }

    public function testExpandsEmptyConstructorWithArguments(): void
    {
        // This is the specific case you originally requested
        $input = <<<'PHP'
<?php
class Foo
{
    public function __construct(
        private string $bar,
    ) {}
}
PHP;

        $expected = <<<'PHP'
<?php
class Foo
{
    public function __construct(
        private string $bar,
    ) {
    }
}
PHP;

        self::assertSame(
            $expected,
            $this->fix($input),
        );
    }

    public function testIgnoresMethodWithContent(): void
    {
        $input = <<<'PHP'
<?php
class Foo
{
    public function bar($a) {
        return $a;
    }
}
PHP;

        // Expect no changes
        self::assertSame(
            $input,
            $this->fix($input),
        );
    }

    public function testIgnoresAbstractMethods(): void
    {
        $input = <<<'PHP'
<?php
interface Foo
{
    public function bar($a);
}
PHP;

        self::assertSame(
            $input,
            $this->fix($input),
        );
    }

    public function testHandlesAnonymousFunctions(): void
    {
        // Anonymous function with args -> expand
        $input = <<<'PHP'
<?php
$fn = function($a) {};
PHP;

        $expected = <<<'PHP'
<?php
$fn = function($a) {
};
PHP;

        self::assertSame(
            $expected,
            $this->fix($input),
        );
    }

    private function fix(
        string $code,
    ): string {
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo('test.php');

        $this->fixer->fix($file, $tokens);

        return $tokens->generateCode();
    }
}
