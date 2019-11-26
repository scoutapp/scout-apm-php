# Scout PHP APM Agent

[![Build Status](https://travis-ci.com/scoutapp/scout-apm-php.svg?branch=master)](https://travis-ci.com/scoutapp/scout-apm-php) [![Latest Stable Version](https://poser.pugx.org/scoutapp/scout-apm-php/v/stable)](https://packagist.org/packages/scoutapp/scout-apm-php) [![License](https://poser.pugx.org/scoutapp/scout-apm-php/license)](https://packagist.org/packages/scoutapp/scout-apm-php)

Email us at support@ScoutAPM.com to get on the beta invite list!

Monitor the performance of PHP apps with Scout's [PHP APM Agent](https://www.scoutapm.com).

Detailed performance metrics and transaction traces are collected once the agent is installed and configured.

## Requirements

PHP Versions: 7.1+

## Quick Start

This package is the base library for the various framework-specific packages.

### Laravel

To install the ScoutAPM Agent for a specific framework, use the specific package instead.

 * [Laravel](https://github.com/scoutapp/scout-apm-laravel)

### Using the base library directly

```php
use Psr\Log\LoggerInterface;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

// It is assumed you are using a PSR Logger
/** @var LoggerInterface $psrLoggerImplementation */

$agent = Agent::fromConfig(
    Config::fromArray([
        ConfigKey::APPLICATION_NAME => 'Your application name',
        ConfigKey::APPLICATION_KEY => 'your scout key',
        ConfigKey::MONITORING_ENABLED => true,
    ]),
    $psrLoggerImplementation
);
// If the core agent is not already running, this will download and run it (from /tmp by default)
$agent->connect();

// Use $agent to record `webTransaction`, `backgroundTransaction`, `instrument` or `tagRequest` as necessary

// Nothing is sent to Scout until you call this - so call this at the end of your request
$agent->send();
```

#### Default log level

By default, the library is *very* noisy in logging by design - this is to help us figure out what is going wrong if you
need assistance. If you are confident everything is working, and you can see data in your Scout dashboard, then you
can increase the minimum log level by adding the following configuration to set the "minimum" log level (which **only**
applies to Scout's logging):

```php
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

/** @var LoggerInterface $psrLoggerImplementation */

$agent = Agent::fromConfig(
    Config::fromArray([
        ConfigKey::APPLICATION_NAME => 'Your application name',
        ConfigKey::APPLICATION_KEY => 'your scout key',
        ConfigKey::MONITORING_ENABLED => true,
        ConfigKey::LOG_LEVEL => LogLevel::ERROR, // <-- add this configuration
    ]),
    $psrLoggerImplementation
);
```

## Monitoring of PHP internal functions

You can enable additional monitoring of internal PHP function executions to measure time taken there. To do so, you need
to install and enable the `scoutapm` PHP extension from PECL, for example:

```bash
$ sudo pecl install scoutapm
```

You may need to add `zend_extension=scoutapm.so` into your `php.ini` to enable the extension.

With the extension enabled, specific IO-bound functions in PHP are monitored, for example `file_get_contents`,
`file_put_contents`, `PDO->exec` and so on.

Alternatively, you can [install from source](https://github.com/scoutapp/scout-apm-php-ext).

## Documentation

For full installation and troubleshooting documentation, visit our [help site](http://docs.scoutapm.com/#php-agent).

## Support

Please contact us at support@ScoutAPM.com or create an issue in this repo.
