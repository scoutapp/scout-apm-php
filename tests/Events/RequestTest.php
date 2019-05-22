<?php
namespace Scoutapm\Events\Tests;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\Events\Request;

/**
 * Test Case for @see \Scoutapm\Events\Request
 */
final class RequestTest extends TestCase
{
    public function testCanBeInitialized()
    {
        $request = new Request(new Agent(), '');
        $this->assertNotNull($request);
    }
}
