# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.11.2] - 2026-08-30

### Changed
- Updated foreline/clean to v2.12.0 (`AbstractBridge` audit fields `createdBy`/`modifiedBy` are now typed against `Domain\User\Aggregate\UserInterface`, fixing load-time covariance fatals in bridge aggregates).

## [2.11.1] - 2026-08-29

### Fixed
- Base ORM `Repository::getParams()` no longer caches queries containing JOINs (`cache_joins` now `false`, the Bitrix ORM default). Joined queries over large tables produce serialized row sets of 100+ MB (one row per M2M combination), which OOMed PHP processes on managed-cache write and could never be stored anyway (memcached 1 MB item limit). Flat queries without JOINs are still cached for 1 hour.

## [2.11.0] - 2026-08-20

### Changed
- Updated foreline/clean to v2.11.0 (adds `Domain\ValueObject` length-limited text VOs: `AbstractLengthLimitedText`, `ShortText`, `Text`, `MediumText`, `LongText`).

## [2.10.0] - 2026-08-20

### Changed
- Updated foreline/clean to v2.10.0 (adds `Domain\ValueObject\LengthAwareStringValueObjectInterface` consumed by `foreline/clean-vo`).

## [2.9.0] - 2026-08-01

### Fixed
- `OrmDataManager`: map ArrayField to text column instead of varchar(255).

### Changed
- Updated foreline/clean to v2.9.0.

## [2.8.0] - 2026-07-14

### Added
- `OrmDataManager`: support for model-driven database field defaults. Default values are read from the associated domain model/entity class and applied to `ADD`/`MODIFY` SQL statements, with boolean values mapped to Bitrix 'Y'/'N' representation.
- `OrmDataManager`: `manageDefault()` to detect and reconcile changes to existing field default values.
- `phpunit.xml` and `tests/bootstrap.php` to enable test execution outside a full Bitrix runtime.

### Changed
- Updated foreline/clean to v2.8.0.

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
