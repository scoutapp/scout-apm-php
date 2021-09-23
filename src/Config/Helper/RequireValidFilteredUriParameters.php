<?php

declare(strict_types=1);

namespace Scoutapm\Config\Helper;

use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function array_values;
use function is_array;
use function is_string;

final class RequireValidFilteredUriParameters
{
    /** @psalm-return list<string> */
    public static function fromConfig(Config $config): array
    {
        /** @var mixed $uriFilteredParameters */
        $uriFilteredParameters = $config->get(ConfigKey::URI_FILTERED_PARAMETERS);

        /** @var list<string> $defaultFilteredParameters */
        $defaultFilteredParameters = (new Config\Source\DefaultSource())->get(ConfigKey::URI_FILTERED_PARAMETERS);

        if (! is_array($uriFilteredParameters)) {
            return $defaultFilteredParameters;
        }

        foreach ($uriFilteredParameters as $filteredParameter) {
            if (! is_string($filteredParameter)) {
                return $defaultFilteredParameters;
            }
        }

        /** @psalm-var array<array-key, string> $uriFilteredParameters */
        return array_values($uriFilteredParameters);
    }
}
