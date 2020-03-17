# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 4.2.0 - 2020-03-17

### Added

- Nothing.

### Changed

- [#173](https://github.com/scoutapp/scout-apm-php/pull/173) Updated to use core-agent 1.2.8

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 4.1.0 - 2020-03-05

### Added

- [#172](https://github.com/scoutapp/scout-apm-php/pull/172) Option to display payload content in debugging logs
- [#164](https://github.com/scoutapp/scout-apm-php/pull/164) Added additional logging and testing around core-agent launching

### Changed

- [#170](https://github.com/scoutapp/scout-apm-php/pull/170) Always use musl instead of trying to detect libc flavour

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#169](https://github.com/scoutapp/scout-apm-php/pull/169) Fixed queue time calculation for various scales

## 4.0.1 - 2020-02-04

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#158](https://github.com/scoutapp/scout-apm-php/pull/158) Fixed bug where cyclic references would cause memory leak in long-running processes

## 4.0.0 - 2020-01-08

### Added

- Nothing.

### Changed

- [#156](https://github.com/scoutapp/scout-apm-php/pull/156) Added configuration option `disabled_instruments` to be used by framework specific bindings
- [#155](https://github.com/scoutapp/scout-apm-php/pull/155) More test coverage

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 3.1.0 - 2019-12-31

### Added

- [#151](https://github.com/scoutapp/scout-apm-php/pull/151) Add check to make sure we have a recent version of the PHP extension
- [#149](https://github.com/scoutapp/scout-apm-php/pull/149) Capture request queue time

### Changed

- [#152](https://github.com/scoutapp/scout-apm-php/pull/152) Added some Agent test coverage
- [#150](https://github.com/scoutapp/scout-apm-php/pull/150) Updated framework/framework version to come from configuration, not hard-coded

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 3.0.0 - 2019-12-19

### Added

- [#148](https://github.com/scoutapp/scout-apm-php/pull/148) **[BC]** Added new method `Scoutapm\ScoutApmAgent::startNewRequest`
  - implementors of `Scoutapm\ScoutApmAgent` will now need to implement this new method

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.1.1 - 2019-12-17

### Added

- Nothing.

### Changed

- [#140](https://github.com/scoutapp/scout-apm-php/pull/140) Increase core version to 1.2.7
  - Fixes some upstream bugs with payload delivery
- [#141](https://github.com/scoutapp/scout-apm-php/pull/141) Only tag args from PHP internal functions if there are any

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#144](https://github.com/scoutapp/scout-apm-php/pull/144) Suppress notices/errors emitted from socket calls
- [#145](https://github.com/scoutapp/scout-apm-php/pull/145) Only register with core agent once per connection
- [#146](https://github.com/scoutapp/scout-apm-php/pull/146) Do not pre-emptively connect if monitoring is disabled

## 2.1.0 - 2019-12-16

### Added

- Reset the request after each call to `send()`

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.0.1 - 2019-12-09

### Added

- Nothing.

### Changed

- [#130](https://github.com/scoutapp/scout-apm-php/pull/130) Correctly handle warnings raised during core agent connections
- [#131](https://github.com/scoutapp/scout-apm-php/pull/131) Support PHP 7.4
- [#133](https://github.com/scoutapp/scout-apm-php/pull/133) Removed incorrect auto detection of SCM subdirectory
- [#134](https://github.com/scoutapp/scout-apm-php/pull/134) Catch exceptions raised whilst sending metadata

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.0.0 - 2019-12-04

### Added

- [#110](https://github.com/scoutapp/scout-apm-php/pull/110) Added `language_version` key to be sent with metadata
- [#111](https://github.com/scoutapp/scout-apm-php/pull/111) [#115](https://github.com/scoutapp/scout-apm-php/pull/115) [#117](https://github.com/scoutapp/scout-apm-php/pull/117) Added more debug logging to isolate issues easier
- [#118](https://github.com/scoutapp/scout-apm-php/pull/118) Added detection of `musl` by checking if `/etc/alpine-release` exists
- [#123](https://github.com/scoutapp/scout-apm-php/pull/123) **[BC]** Third parameter for `\Scoutapm\Agent::fromConfig` is now a `\Psr\SimpleCache\CacheInterface` implementation (`null`-able)
  - Unlikely to affect most customers, unless they explicitly provided a `\Scoutapm\Connector\Connector` implementation to the `Agent`

### Changed

- **[BC]** `\Scoutapm\Connector\Connector::sendCommand` now returns `string` not `bool`
  - Unlikely to affect most customers, unless they wrote a custom implementation of `\Scoutapm\Connector\Connector`
- **[BC]** `\Scoutapm\Agent::fromConfig()` second parameter for a `\Psr\Log\LoggerInterface` implementation is no longer optional
  - You should pass in an implementation of `\Psr\Log\LoggerInterface` as the second parameter
  - If you do not want logging, you can use `\Psr\Log\NullLogger` (although this is not advisable)
- [#127](https://github.com/scoutapp/scout-apm-php/pull/127) Updated core agent version to `1.2.6`

### Deprecated

- Nothing.

### Removed

- **[BC]** `\Scoutapm\Agent::__construct` is now private
  - Use the named constructor `\Scoutapm\Agent::fromConfig()` instead
- **[BC]** `\Scoutapm\Agent::fromDefaults()` named constructor was removed
  - For exactly matching behaviour, use `::fromConfig(new \Scoutapm\Config(), new \Psr\Log\NullLogger())`

### Fixed

- [#126](https://github.com/scoutapp/scout-apm-php/pull/126) Fixing bug with instrumentation delivery to dashboard
- [#124](https://github.com/scoutapp/scout-apm-php/pull/124) Handle warning that was escaping from `socket_connect`

## 1.0.0 - 2019-11-05

### Added

- Nothing.

### Changed

- [#106](https://github.com/scoutapp/scout-apm-php/pull/106) Improved error message when invalid log level given for `log_level` configuration
- [#109](https://github.com/scoutapp/scout-apm-php/pull/109) Updated installation instructions to remove `-alpha` flag from PHP extension

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.2.3 - 2019-10-22

### Added

- [#83](https://github.com/scoutapp/scout-apm-php/pull/83) New `\Scoutapm\Config\ConfigKey` class containing `public const`s for configuration key names
- [#83](https://github.com/scoutapp/scout-apm-php/pull/83) Added config key `log_level` which overrides Scout APM's minimum log level
- [#88](https://github.com/scoutapp/scout-apm-php/pull/88) Added more new config keys:
  - `application_root` (defaults to `composer.json` location, or `$_SERVER['DOCUMENT_ROOT']`
  - `scm_subdirectory` (defaults to `.git` location, or `application_root` value)
  - `revision_sha` (defaults to version detected by `ocramius/package-versions`)
  - `hostname` (defaults to value of `gethostname()`)
  - `core_agent_permissions` (defaults to `0777`)
- Added warning when `name` or `key` configurations are not set
- [#93](https://github.com/scoutapp/scout-apm-php/pull/93) All log messages are prepended with `[Scout]`
- [#81](https://github.com/scoutapp/scout-apm-php/pull/81) Build status badges added to README
- [#89](https://github.com/scoutapp/scout-apm-php/pull/89) Added memory usage statistics to request
- [#96](https://github.com/scoutapp/scout-apm-php/pull/96) Suppress backtraces for Controller, Job, and Middleware spans
- [#102](https://github.com/scoutapp/scout-apm-php/pull/103) Tag the request URI automatically with `$_SERVER['REQUEST_URI']` or override with alternative
- [#103](https://github.com/scoutapp/scout-apm-php/pull/103) Update CoreAgent to 1.2.4

### Changed

- [#83](https://github.com/scoutapp/scout-apm-php/pull/83) **[BC]** Renamed the following configuration keys
  - `log_level` => `core_agent_log_level`
  - `log_file` => `core_agent_log_file`
  - `config_file` => `core_agent_config_file`
  - `socket_path` => `core_agent_socket_path`
  - `download_url` => `core_agent_download_url`
- [#84](https://github.com/scoutapp/scout-apm-php/pull/84) Improved stack trace filtering
- [#92](https://github.com/scoutapp/scout-apm-php/pull/92) CI updated to use `pecl` to install scoutapm extension

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

 - [#91](https://github.com/scoutapp/scout-apm-php/pull/91) Fixed missing request stop timestamp

## 0.2.2 - 2019-09-26

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#80](https://github.com/scoutapp/scout-apm-php/pull/80) Corrected naming of core agent config values

## 0.2.1 - 2019-09-25

### Added

- Nothing.

### Changed

- [#78](https://github.com/scoutapp/scout-apm-php/pull/78) Lock CoreAgent version to 1.2.2
- [#76](https://github.com/scoutapp/scout-apm-php/pull/76) Always provide `--log-file` parameter for core agent
- [#72](https://github.com/scoutapp/scout-apm-php/pull/72) Added an interface for the agent `Scoutapm\ScoutApmAgent`
- [#55](https://github.com/scoutapp/scout-apm-php/pull/55) Support fetching function call arguments from PHP ext

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.2.0 - 2019-09-11

### Added

- Nothing.

### Changed

- [#47](https://github.com/scoutapp/scout-apm-php/pull/47) Internal data model now preserves order
- [#50](https://github.com/scoutapp/scout-apm-php/pull/50) Loosen several dependency version requirements
- [#48](https://github.com/scoutapp/scout-apm-php/pull/48) Licensed as MIT
- [#42](https://github.com/scoutapp/scout-apm-php/pull/42) [#54](https://github.com/scoutapp/scout-apm-php/pull/54) Initial support for Scout Native Extension
- [#43](https://github.com/scoutapp/scout-apm-php/pull/43) Updated default socket path
- [#46](https://github.com/scoutapp/scout-apm-php/pull/46) Added integration test execution into CI
- [#39](https://github.com/scoutapp/scout-apm-php/pull/39) Fetch `git_sha` metadata from `ocramius/package-versions`

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.1.1 - 2019-08-22

### Added

- [#33](https://github.com/scoutapp/scout-apm-php/pull/33) Added `ignore` configuration option
- [#32](https://github.com/scoutapp/scout-apm-php/pull/32) Pass metadata along with each request made to Scout
- [#31](https://github.com/scoutapp/scout-apm-php/pull/31) Added coercion for JSON-formatted configuration values

### Changed

- [#34](https://github.com/scoutapp/scout-apm-php/pull/34) Large refactoring internally
  - API for creating the agent should be `Agent::fromConfig(Config::fromArray([]))` at minimum
  - Most of what is intended to be "internal" with no promise of BC is now marked `@internal`
  - Strict type hints introduced throughout the library
  - Internal interfaces for `\Scoutapm\Connector\Connector`, `\Scoutapm\CoreAgent\Manager`, etc. introduced
  - Applied `doctrine/coding-standard` throughout
  - Applied Psalm static analysis throughout

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#38](https://github.com/scoutapp/scout-apm-php/pull/38) Fixed agent launch bug

## 0.1.0 - 2019-08-05

### Added

- Initial Release. See documentation at https://docs.scoutapm.com

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
