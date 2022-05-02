#!/bin/bash

set -uo pipefail

cd "$(dirname "$0")/../.." || exit 2

# This file is a hack to suppress warnings from Roave BC check
# Based on: https://github.com/guzzle/guzzle/blob/7a30f3bc91b3ab57860efbe8272649cc23dbbcc2/.github/workflows/bc.entrypoint

echo "Running BC check, please wait..."

# Capture output to variable AND print it
OUTPUT=$(vendor/bin/roave-backward-compatibility-check --format=markdown "$@" 2>&1)

# Remove rows we want to suppress
#OUTPUT=`echo "$OUTPUT" | sed '/Roave\\\BetterReflection\\\Reflection\\\ReflectionClass "Symfony\\\Component\\\HttpKernel\\\Event\\\FilterControllerEvent" could not be found in the located source/'d`
#OUTPUT=`echo "$OUTPUT" | sed '/Roave\\\BetterReflection\\\Reflection\\\ReflectionClass "Scoutapm\\\ScoutApmBundle\\\Twig\\\TwigMethods" could not be found in the located source/'d`
#OUTPUT=`echo "$OUTPUT" | sed '/Value of constant Twig\\\Environment::.* changed from .* to .*/'d`
OUTPUT=`echo "$OUTPUT" | sed '/Method Illuminate\\\Support\\\Facades\\\Facade::.*() was removed/'d`

# Number of rows we found with "[BC]" in them
BC_BREAKS=`echo "$OUTPUT" | grep -o '\[BC\]' | wc -l | awk '{ print $1 }'`

# The last row of the output is "X backwards-incompatible changes detected". Find X.
STATED_BREAKS=`echo "$OUTPUT" | tail -n 1 | awk -F' ' '{ print $1 }'`

EXPECTED_STATED_BREAKS=2

echo "$OUTPUT"
echo "Lines with [BC] not filtered: $BC_BREAKS"
echo "Stated breaks: $STATED_BREAKS out of expected $EXPECTED_STATED_BREAKS"

# If
#   We found "[BC]" in the command output after we removed suppressed lines
# OR
#   We have suppressed X number of BC breaks. If $STATED_BREAKS is larger than X
# THEN
#   exit 1
if [ $BC_BREAKS -gt 0 ] || [ $STATED_BREAKS -gt $EXPECTED_STATED_BREAKS ]; then
    echo "EXIT 1"
    exit 1
fi

# No BC breaks found
echo "EXIT 0"
exit 0
