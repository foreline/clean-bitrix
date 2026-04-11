# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-04-11

### Added
- DI Container bridge for Bitrix CMS integration with BitrixBridge (module loading and service registration) and BitrixIBlockHelper (IBlock operations).

### Changed
- Bitrix infrastructure code quality: use imported class names instead of fully qualified references.
- Updated developer workflow documentation for core dependency version alignment.


## [1.1.8] - 2026-04-10

### Changed
- Updated foreline/clean to v1.1.4.

## [1.1.7] - 2026-03-31

### Fixed
- ORM: Skip newly-added fields in updateTable() nullable check to prevent errors when modifying fields added in the same call.

## [1.1.6]

[Previous versions documented in git history]


