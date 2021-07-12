<?php

/**
 * Stub file useful for IDEs like PhpStorm where definitions for the `scout-apm-php-ext` PHP extension are not available
 */

/** @psalm-return list<array{function:string, entered:float, exited: float, time_taken: float, argv: mixed[]}> */
function scoutapm_get_calls() : array {}
function scoutapm_enable_instrumentation(bool $enabled): void {}
/** @return list<string> */
function scoutapm_list_instrumented_functions(): array {}
