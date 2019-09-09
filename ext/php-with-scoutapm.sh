#!/usr/bin/env bash

set -euxo pipefail

cd "$(dirname "$0")/../"

PHP_PATH=$(which php)

EXTPATH=scoutapm.so

$PHP_PATH -d zend_extension=$EXTPATH -r "if (!extension_loaded('scoutapm')) { echo 'ERROR - Scout APM extension failed to load' . PHP_EOL; } exit(extension_loaded('scoutapm')?0:1);"

$PHP_PATH -d zend_extension=$EXTPATH "$@"
