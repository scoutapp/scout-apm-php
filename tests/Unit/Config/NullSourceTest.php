<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\NullSource;

final class NullSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $defaults = new NullSource();
        $this->assertTrue($defaults->hasKey('apiVersion'));
        $this->assertTrue($defaults->hasKey('notAValue'));
    }

    public function testGet() : void
    {
        $defaults = new NullSource();
        $this->assertEquals(null, $defaults->get('apiVersion'));
        $this->assertEquals(null, $defaults->get('weirdThing'));
    }
}
