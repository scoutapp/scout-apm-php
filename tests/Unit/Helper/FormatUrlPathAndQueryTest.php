<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Helper\FormatUrlPathAndQuery;

/** @covers \Scoutapm\Helper\FormatUrlPathAndQuery */
final class FormatUrlPathAndQueryTest extends TestCase
{
    /** @psalm-return array<string, array{0: ConfigKey::URI_REPORTING_*, 1: string, 2: string}> */
    public function urlFilteringProvider(): array
    {
        return [
            'full' => [ConfigKey::URI_REPORTING_FULL_PATH, '/path/to/thing.html', '/path/to/thing.html'],
            'path' => [ConfigKey::URI_REPORTING_PATH_ONLY, '/path/to/thing.html', '/path/to/thing.html'],
            'filtered' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html', '/path/to/thing.html'],
            'full_query' => [ConfigKey::URI_REPORTING_FULL_PATH, '/path/to/thing.html?a=b&c=d&e[]=1&e[]=2', '/path/to/thing.html?a=b&c=d&e[]=1&e[]=2'],
            'path_query' => [ConfigKey::URI_REPORTING_PATH_ONLY, '/path/to/thing.html?a=b&c=d&e[]=1&e[]=2', '/path/to/thing.html'],
            'filtered_query_last' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?a=b&e[]=1&e[]=2&filteredParam=boo', '/path/to/thing.html?a=b&e[0]=1&e[1]=2'],
            'filtered_query_first' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?filteredParam=boo&c=d&e[]=1&e[]=2', '/path/to/thing.html?c=d&e[0]=1&e[1]=2'],
            'filtered_query_array' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?a=b&c=d&filteredParam[]=1&filteredParam[]=2', '/path/to/thing.html?a=b&c=d'],
            'filtered_query_gone' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?filteredParam=boo', '/path/to/thing.html'],
            'filtered_query_array_gone' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?filteredParam[]=1&filteredParam[]=2', '/path/to/thing.html'],
            'full_fragment' => [ConfigKey::URI_REPORTING_FULL_PATH, '/path/to/thing.html#fragment', '/path/to/thing.html#fragment'],
            'path_fragment' => [ConfigKey::URI_REPORTING_PATH_ONLY, '/path/to/thing.html#fragment', '/path/to/thing.html#fragment'],
            'filtered_fragment' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html#fragment', '/path/to/thing.html#fragment'],
            'full_query_fragment' => [ConfigKey::URI_REPORTING_FULL_PATH, '/path/to/thing.html?a=b&c=d&e[]=1&e[]=2#fragment', '/path/to/thing.html?a=b&c=d&e[]=1&e[]=2#fragment'],
            'path_query_fragment' => [ConfigKey::URI_REPORTING_PATH_ONLY, '/path/to/thing.html?a=b&c=d&e[]=1&e[]=2#fragment', '/path/to/thing.html#fragment'],
            'filtered_query_fragment_last' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?a=b&e[]=1&e[]=2&filteredParam=boo#fragment', '/path/to/thing.html?a=b&e[0]=1&e[1]=2#fragment'],
            'filtered_query_fragment_first' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?filteredParam=boo&c=d&e[]=1&e[]=2#fragment', '/path/to/thing.html?c=d&e[0]=1&e[1]=2#fragment'],
            'filtered_query_fragment_array' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?a=b&c=d&filteredParam[]=1&filteredParam[]=2#fragment', '/path/to/thing.html?a=b&c=d#fragment'],
            'filtered_query_fragment_gone' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?filteredParam=boo#fragment', '/path/to/thing.html#fragment'],
            'filtered_query_fragment_array_gone' => [ConfigKey::URI_REPORTING_FILTERED, '/path/to/thing.html?filteredParam[]=1&filteredParam[]=2#fragment', '/path/to/thing.html#fragment'],
        ];
    }

    /**
     * @psalm-param ConfigKey::URI_REPORTING_* $configuration
     *
     * @psalm-suppress PossiblyInvalidArgument
     * @dataProvider urlFilteringProvider
     */
    public function testUrlPathIsFilteredCorrectly(string $configuration, string $subjectUrlPath, string $expectedFormattedPath): void
    {
        self::assertSame($expectedFormattedPath, FormatUrlPathAndQuery::forUriReportingConfiguration($configuration, ['filteredParam'], $subjectUrlPath));
    }
}
