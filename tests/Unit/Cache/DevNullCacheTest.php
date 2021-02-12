<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Cache;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Scoutapm\Cache\DevNullCache;

use function uniqid;

/** @covers \Scoutapm\Cache\DevNullCache */
final class DevNullCacheTest extends TestCase
{
    /** @throws InvalidArgumentException */
    public function testCacheGetReturnsDefaultValueAfterSet(): void
    {
        $cache        = new DevNullCache();
        $someKey      = uniqid('someKey', true);
        $defaultValue = uniqid('defaultValue', true);

        self::assertTrue($cache->set($someKey, uniqid('actualValue', true)));

        self::assertFalse($cache->has($someKey));
        self::assertSame($defaultValue, $cache->get($someKey, $defaultValue));
    }

    /** @throws InvalidArgumentException */
    public function testCacheGetReturnsNullAfterSetWhenNoDefaultGiven(): void
    {
        $cache   = new DevNullCache();
        $someKey = uniqid('someKey', true);

        self::assertTrue($cache->set($someKey, uniqid('actualValue', true)));

        self::assertFalse($cache->has($someKey));
        self::assertNull($cache->get($someKey));
    }

    /** @throws InvalidArgumentException */
    public function testCacheGetReturnsDefaultValueAfterDelete(): void
    {
        $cache        = new DevNullCache();
        $someKey      = uniqid('someKey', true);
        $defaultValue = uniqid('defaultValue', true);

        self::assertTrue($cache->set($someKey, uniqid('actualValue', true)));
        self::assertTrue($cache->delete($someKey));

        self::assertFalse($cache->has($someKey));
        self::assertSame($defaultValue, $cache->get($someKey, $defaultValue));
    }

    /** @throws InvalidArgumentException */
    public function testCacheGetReturnsDefaultValueAfterClear(): void
    {
        $cache        = new DevNullCache();
        $someKey      = uniqid('someKey', true);
        $defaultValue = uniqid('defaultValue', true);

        self::assertTrue($cache->set($someKey, uniqid('actualValue', true)));
        self::assertTrue($cache->clear());

        self::assertFalse($cache->has($someKey));
        self::assertSame($defaultValue, $cache->get($someKey, $defaultValue));
    }

    /** @throws InvalidArgumentException */
    public function testCacheGetMultipleReturnsDefaultValueAfterSetMultiple(): void
    {
        $cache        = new DevNullCache();
        $someKey      = uniqid('someKey', true);
        $defaultValue = uniqid('defaultValue', true);

        self::assertTrue($cache->setMultiple([$someKey => uniqid('actualValue', true)]));

        self::assertFalse($cache->has($someKey));
        self::assertEquals([$someKey => $defaultValue], $cache->getMultiple([$someKey], $defaultValue));
    }

    /** @throws InvalidArgumentException */
    public function testCacheGetMultipleReturnsNullAfterSetMultipleWithNoDefaultValue(): void
    {
        $cache   = new DevNullCache();
        $someKey = uniqid('someKey', true);

        self::assertTrue($cache->setMultiple([$someKey => uniqid('actualValue', true)]));

        self::assertFalse($cache->has($someKey));
        self::assertEquals([$someKey => null], $cache->getMultiple([$someKey]));
    }

    /** @throws InvalidArgumentException */
    public function testCacheGetMultipleReturnsDefaultValueAfterDeleteMultiple(): void
    {
        $cache        = new DevNullCache();
        $someKey      = uniqid('someKey', true);
        $defaultValue = uniqid('defaultValue', true);

        self::assertTrue($cache->setMultiple([$someKey => uniqid('actualValue', true)]));
        self::assertTrue($cache->deleteMultiple([$someKey]));

        self::assertFalse($cache->has($someKey));
        self::assertEquals([$someKey => $defaultValue], $cache->getMultiple([$someKey], $defaultValue));
    }
}
