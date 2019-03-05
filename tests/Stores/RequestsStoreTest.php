<?php
namespace Scoutapm\Tests\Stores;

use \Scoutapm\RequestsStore;
use \Scoutapm\Events\Request;
use \Scoutapm\Exception\Request\DuplicateRequestNameException;
use \PHPUnit\Framework\TestCase;

/**
 * Test Case for @see \Scoutapm\RequestsStore
 */
final class RequestsStoreTest extends TestCase {

  /**
   * @covers \Scoutapm\RequestsStore::register
   * @covers \Scoutapm\RequestsStore::get
   */
  public function testRequestRegistrationAndGet() {
    $store = new RequestsStore();
    $name  = 'test';
    $request   = new Request($name, []);

    $this->assertTrue($store->isEmpty());

    $store->register($request);
    $proof = $store->get($name);

    $this->assertEquals($request, $proof);
    $this->assertNotNull($proof);

    $this->assertFalse($store->isEmpty());
  }

  /**
   * @depends testRequestRegistrationAndGet
   *
   * @covers \Scoutapm\RequestsStore::register
   */
  public function testDuplicateRequestRegistration() {
    $this->expectException(\Scoutapm\Exception\Request\DuplicateRequestNameException::class);
    $store = new RequestsStore();
    $name  = 'test';
    $request   = new Request($name, []);

    $store->register($request);
    $store->register($request);
  }

  /**
   * @depends testRequestRegistrationAndGet
   *
   * @covers \Scoutapm\RequestsStore::get
   */
  public function testGetUnknownRequest() {
    $store = new RequestsStore();
    $this->assertNull($store->get('unknown'));
  }

}
