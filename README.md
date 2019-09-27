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
use Scoutapm\Agent;
use Scoutapm\Config;

$agent = Agent::fromConfig(Config::fromArray([
    'name' => 'Your application name',
    'key' => 'your scout key',
    'monitor' => true,
]));
// If the core agent is not already running, this will download and run it (from /tmp by default)
$agent->connect();

// Use $agent to record `webTransaction`, `backgroundTransaction`, `instrument` or `tagRequest` as necessary

// Nothing is sent to Scout until you call this - so call this at the end of your request
$agent->send();
```

## Documentation

For full installation and troubleshooting documentation, visit our [help site](http://docs.scoutapm.com/#php-agent).

## Support

Please contact us at support@ScoutAPM.com or create an issue in this repo.
