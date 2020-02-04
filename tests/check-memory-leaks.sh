#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")"

SINGLE=$(RUN_COUNT=1 php isolated-memory-test.php)
echo "Single execution used: $SINGLE bytes"

MULTIPLE=$(RUN_COUNT=1000 php isolated-memory-test.php)
echo "1000 executions used: $MULTIPLE bytes"

if [ "$SINGLE" = "$MULTIPLE" ]; then
  echo "No memory leak detected"
  exit 0
else
  echo "Potential memory leak!"
  exit 1
fi
