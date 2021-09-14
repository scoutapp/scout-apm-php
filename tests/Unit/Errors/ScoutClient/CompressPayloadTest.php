<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors\ScoutClient;

use PHPUnit\Framework\TestCase;
use Scoutapm\Errors\ScoutClient\CompressPayload;

use function gzdecode;
use function hex2bin;

/** @covers \Scoutapm\Errors\ScoutClient\CompressPayload */
final class CompressPayloadTest extends TestCase
{
    public function testCompressPayloadCorrectly(): void
    {
        $compressedPayload = (new CompressPayload())('test');

        self::assertSame(
            hex2bin('1f8b08000000000000032b492d2e01000c7e7fd804000000'),
            $compressedPayload
        );

        self::assertSame('test', gzdecode($compressedPayload));
    }
}
