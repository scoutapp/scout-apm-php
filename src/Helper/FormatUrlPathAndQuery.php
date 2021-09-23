<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use Scoutapm\Config\ConfigKey;

use function array_key_exists;
use function count;
use function http_build_query;
use function is_string;
use function parse_str;
use function parse_url;
use function urldecode;

/** @internal */
final class FormatUrlPathAndQuery
{
    /**
     * @psalm-pure
     * @psalm-param ConfigKey::URI_REPORTING_* $uriReportingConfiguration
     * @psalm-param list<string> $filteredParameters
     */
    public static function forUriReportingConfiguration(string $uriReportingConfiguration, array $filteredParameters, string $subjectUrlPath): string
    {
        if ($uriReportingConfiguration === ConfigKey::URI_REPORTING_FULL_PATH) {
            return $subjectUrlPath;
        }

        $urlParts = parse_url($subjectUrlPath);
        $path     = array_key_exists('path', $urlParts) && is_string($urlParts['path']) ? $urlParts['path'] : '/';
        $fragment = array_key_exists('fragment', $urlParts) && is_string($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';

        if ($uriReportingConfiguration === ConfigKey::URI_REPORTING_PATH_ONLY) {
            return $path . $fragment;
        }

        $queryString = array_key_exists('query', $urlParts) && is_string($urlParts['query']) ? $urlParts['query'] : '';

        /** @psalm-suppress ImpureFunctionCall - when called with second param, this should be a pure function call */
        parse_str($queryString, $queryParts);

        $filteredQuery = FilterParameters::forUriReportingConfiguration($filteredParameters, $queryParts);

        if (! count($filteredQuery)) {
            return $path . $fragment;
        }

        return $path . '?' . urldecode(http_build_query($filteredQuery)) . $fragment;
    }
}
