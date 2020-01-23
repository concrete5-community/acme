<?php

namespace Acme\Test;

use PHPUnit_Framework_TestCase;

class AutoloadTest extends PHPUnit_Framework_TestCase
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
