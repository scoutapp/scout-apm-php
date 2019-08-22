# Changelog

## Pending

### Added

### Fixed

## [0.1.1] 2019-08-22

### Changed

 - API for creating the agent should be `Agent::fromConfig(Config::fromArray([]))` at minimum
 - Most of what is intended to be "internal" with no promise of BC is now marked `@internal`
 - Strict type hints introduced throughout the library
 - Internal interfaces for `\Scoutapm\Connector\Connector`, `\Scoutapm\CoreAgent\Manager`, etc. introduced
 - Applied `doctrine/coding-standard` throughout
 - Applied Psalm static analysis throughout
 - Large amount of general internal refactoring

## [0.1] 2019-08-05

Initial Release. See documentation at https://docs.scoutapm.com

