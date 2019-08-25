#!/usr/bin/env bash

set -euxo pipefail

cd "$(dirname "$0")/../"

WORKING_DIRECTORY=$(pwd)
PHP_API=$(php -i | grep 'PHP API' | sed -e 's/PHP API => //')
EXTPATH="$WORKING_DIRECTORY/ext/scoutapm-$PHP_API.so"

if [[ ! -f $EXTPATH ]]; then
  echo "Extension was not found for PHP API $PHP_API (at $EXTPATH)"
  exit 1
fi

echo "PHP API version is: $PHP_API"
echo "Extension path is: $EXTPATH"

php -d zend_extension=$EXTPATH "$@"
