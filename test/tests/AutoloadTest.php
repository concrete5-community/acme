<?php

namespace Acme\Test;

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    public function provideClassNames()
    {
        return [
            [\Acme\Entity\Account::class],
        ];
    }

    /**
     * @dataProvider provideClassNames
     *
     * @param string $className
     */
    public function testClassExist($className)
    {
        $this->assertTrue(class_exists($className), "The class {$className} should be resolved.");
    }
}
