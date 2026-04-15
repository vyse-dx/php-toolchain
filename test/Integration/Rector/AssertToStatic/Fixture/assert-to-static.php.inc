<?php

namespace Vyse\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MyTest extends WebTestCase
{
    public function testSomething(): void
    {
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Hello!');
    }
}
-----
<?php

namespace Vyse\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MyTest extends WebTestCase
{
    public function testSomething(): void
    {
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Hello!');
    }
}
