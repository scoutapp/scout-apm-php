# Changelog

## Pending

### Changed

 - Added `language_version` key to be sent with metadata (#110)
 - Added more debug logging to isolate issues easier (#111, #115)
 - Added detection of `musl` by checking if `/etc/alpine-release` exists (#118)
 - **[BC]** `\Scoutapm\Connector\Connector::sendCommand` now returns `string` not `bool`
 - **[BC]** `\Scoutapm\Agent::fromConfig()` second parameter for a `\Psr\Log\LoggerInterface` implementation is no longer optional
   - You should pass in an implementation of `\Psr\Log\LoggerInterface` as the second parameter
   - If you do not want logging, you can use `\Psr\Log\NullLogger` (although this is not advisable)
 - **[BC]** `\Scoutapm\Agent::__construct` is now private
   - Use the named constructor `\Scoutapm\Agent::fromConfig()` instead

### Removed
 - **[BC]** `\Scoutapm\Agent::fromDefaults()` named constructor was removed
   - For exactly matching behaviour, use `::fromConfig(new \Scoutapm\Config(), new \Psr\Log\NullLogger())`

## [1.0.0] 2019-11-05

### Changed

 - Improved error message when invalid log level given for `log_level` configuration (#106)
 - Updated installation instructions to remove `-alpha` flag from PHP extension (#109)

## [0.2.3] 2019-10-22

### Added

 - New `\Scoutapm\Config\ConfigKey` class containing `public const`s for configuration key names (#83)
 - Added config key `log_level` which overrides Scout APM's minimum log level (#83)
 - Added more new config keys (#88):
   - `application_root` (defaults to `composer.json` location, or `$_SERVER['DOCUMENT_ROOT']`
   - `scm_subdirectory` (defaults to `.git` location, or `application_root` value)
   - `revision_sha` (defaults to version detected by `ocramius/package-versions`)
   - `hostname` (defaults to value of `gethostname()`)
   - `core_agent_permissions` (defaults to `0777`)
 - Added warning when `name` or `key` configurations are not set
 - All log messages are prepended with `[Scout]` (#93)
 - Build status badges added to README (#81)
 - Added memory usage statistics to request (#89)
 - Suppress backtraces for Controller, Job, and Middleware spans (#96)
 - Tag the request URI automatically with `$_SERVER['REQUEST_URI']` or override with alternative (#102)
 - Update CoreAgent to 1.2.4 (#103)

### Fixed

 - Fixed missing request stop timestamp (#91)

### Changed

 - **[BC]** Renamed the following configuration keys (#83)
   - `log_level` => `core_agent_log_level`
   - `log_file` => `core_agent_log_file`
   - `config_file` => `core_agent_config_file`
   - `socket_path` => `core_agent_socket_path`
   - `download_url` => `core_agent_download_url`
 - Improved stack trace filtering (#84)
 - CI updated to use `pecl` to install scoutapm extension (#92)

## [0.2.2] 2019-09-26

### Fixed

 - Corrected naming of core agent config values (#80)

## [0.2.1] 2019-09-25

### Changed

 - Lock CoreAgent version to 1.2.2 (#78)
 - Always provide `--log-file` parameter for core agent (#76)
 - Added an interface for the agent `Scoutapm\ScoutApmAgent` (#72)
 - Support fetching function call arguments from PHP ext (#55)

## [0.2.0] 2019-09-11

### Changed

 - Internal data model now preserves order (#47)
 - Loosen several dependency version requirements (#50)
 - Licensed as MIT (#48)
 - Initial support for Scout Native Extension (#42, #54)
 - Updated default socket path (#43)
 - Added integration test execution into CI (#46)
 - Fetch `git_sha` metadata from `ocramius/package-versions` (#39)

## [0.1.1] 2019-08-22

### Changed

 - Fixed agent launch bug (#38)
 - Large refactoring internally (#34)
   - API for creating the agent should be `Agent::fromConfig(Config::fromArray([]))` at minimum
   - Most of what is intended to be "internal" with no promise of BC is now marked `@internal`
   - Strict type hints introduced throughout the library
   - Internal interfaces for `\Scoutapm\Connector\Connector`, `\Scoutapm\CoreAgent\Manager`, etc. introduced
   - Applied `doctrine/coding-standard` throughout
   - Applied Psalm static analysis throughout
 - Added `ignore` configuration option (#33)
 - Pass metadata along with each request made to Scout (#32)
 - Added coercion for JSON-formatted configuration values (#31)

## [0.1] 2019-08-05

Initial Release. See documentation at https://docs.scoutapm.com

