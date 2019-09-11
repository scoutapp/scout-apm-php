# Changelog

## Pending

### Added

### Fixed


## [0.2.0] 2019-09-11

### Changed

- Internal data model now perserves order (#47)
- Loosen several depencency version requirements (#50)
- Licensed as MIT
- Initial support for Scout Native Extension (#42, #54)

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

