#!/usr/bin/env bash

set -euo pipefail

EXPECTED_PARAMS="start --daemonize true --log-file /tmp/core-agent.log --log-level TRACE --config-file /tmp/core-agent-config.ini --socket /tmp/socket-path.sock"
ACTUAL_PARAMS=$*

if [ "$ACTUAL_PARAMS" != "$EXPECTED_PARAMS" ]; then
  >&2 printf "Script params did not match expectations.\n\nExpected: %s\nActual  : %s\n" "$EXPECTED_PARAMS" "$ACTUAL_PARAMS"
  exit 1
fi

exit 0
