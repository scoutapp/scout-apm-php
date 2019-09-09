<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use function extension_loaded;

abstract class TestHelper
{
    public static function scoutApmExtensionAvailable() : bool
    {
        return extension_loaded('scoutapm');
    }
}
