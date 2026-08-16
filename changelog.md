# Changelog

All notable changes to `Schedules` will be documented in this file.

## Unreleased

### Added
- Runtime evaluator (`schedules:evaluate`) with configurable scheduler registration.
- `Schedulable` contract and `InteractsWithSchedule` trait for host models.
- Soft-delete lifecycle cascades and delete guards for types / type notifications.
- Threshold repetition (`last_repetition`, `repeat_every`) and urgency payload.
- Invalid `TypeNotification` repetition configurations are rejected on save and via form validation (HTTP 422).
- Invalid `Type.models` entries are rejected on save and via form validation (must reference `applicableTo`, include `source` or `method`, and resolve to allowlisted getters).
- Scheduled notification dispatch history table.
- Incremental upgrade migrations from v2.5.1 (see `UPGRADING.md`).

### Changed
- Required schedule domain columns are enforced (`NOT NULL` where the domain demands it).
- Unique pair `(schedule_id, type_notification_id)` on scheduled notifications.
- Configuration validation fails fast on invalid models or role models.
- `TypeNotification::repeatingMeasurementUnit()` resolves the configured
  `measurementunits.models.measurementUnit.class` (same as `measurementUnit()`).
- `TypeNotification.type_id` is immutable after creation (HTTP update rejects it;
  model save throws if reassigned).

### Removed
- Placeholder `license.md` (MIT text lives in `LICENSE` only).
