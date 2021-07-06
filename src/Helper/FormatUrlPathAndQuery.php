<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use Scoutapm\Config\ConfigKey;

/** @internal */
abstract class FormatUrlPathAndQuery
{
    /**
     * @psalm-pure
     * @psalm-param ConfigKey::URI_REPORTING_* $uriReportingConfiguration
     * @psalm-param list<string> $filteredParameters
     */
    public static function forUriReportingConfiguration(string $uriReportingConfiguration, array $filteredParameters, string $subjectUrlPath): string
    {
        return $subjectUrlPath;
    }
}
