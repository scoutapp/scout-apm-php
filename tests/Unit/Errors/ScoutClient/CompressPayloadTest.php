<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors\ScoutClient;

use PHPUnit\Framework\TestCase;
use Scoutapm\Errors\ScoutClient\CompressPayload;

use function bin2hex;
use function gzdecode;
use function substr;

/** @covers \Scoutapm\Errors\ScoutClient\CompressPayload */
final class CompressPayloadTest extends TestCase
{
    public function testCompressPayloadCorrectly(): void
    {
        $compressedPayload = (new CompressPayload())('test');

        // Header takes first 20 chars (10 bytes), e.g. 1f8b0800000000000003 (on Linux)
        // Then the data itself, e.g. 2b492d2e0100
        // Footer takes last 16 chars (8 bytes), e.g. 0c7e7fd804000000

        self::assertSame(
            '2b492d2e0100',
            substr(bin2hex($compressedPayload), 20, -16)
        );

        self::assertSame('test', gzdecode($compressedPayload));
    }
}
