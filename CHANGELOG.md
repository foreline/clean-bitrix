# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.3.0] - 2026-05-04

### Added
- UserRepository: add avatar file reference mapping via Bitrix ORM Relations for direct file object access.

## [2.2.3] - 2026-04-29

### Fixed
- FileRepository: disable cache for file repository queries to ensure fresh data.
- LifecycleStatusViewHelper: add missing UI classes (ui-label-inner, ui-label-fill) to lifecycle status chip markup.

## [2.2.2] - 2026-04-27

### Added
- LifecycleStatusViewHelper: add `renderBadge()` for rendering lifecycle status badges in list grid cells.
- LifecycleStatusViewHelper: add `getInProgressCodes()` and `getFinalCodes()` for filtering enum cases by lifecycle state.
- LifecycleStatusViewHelper: add `colorToken()` convenience accessor for palette CSS tokens.

### Changed
- LifecycleStatusViewHelper: refactor HTML escaping logic into dedicated `escape()` helper method.

## [2.2.1] - 2026-04-27

### Changed
- Updated foreline/clean to v2.6.3.

## [2.2.0] - 2026-04-27

### Added
- LifecycleStatusViewHelper for rendering lifecycle status as colored chips in the UI.

### Changed
- Updated foreline/clean to v2.5.1.

## [2.1.0] - 2026-04-26

### Changed
- Updated foreline/clean to v2.4.0 with enhanced architecture patterns.
- Updated Symfony polyfills (mbstring, php83) to v1.37.0.

## [2.0.0] - 2026-04-25

### Changed
- Upgraded foreline/clean to v2.0.0 and clean-ui to v1.1.0 for new features and bug fixes.
- Updated foreline/clean to v1.2.1.

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
