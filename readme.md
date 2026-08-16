# Schedules

Schedules and deadlines.

See also [UPGRADING.md](UPGRADING.md) when moving from v2.5.1.

## Installation

```bash
composer require ilbronza/schedules
php artisan vendor:publish --tag=schedules.config
php artisan migrate
```

The package models extend the CRUD base model stack and therefore need
`spatie/laravel-activitylog` at runtime (declared in `composer.json`).

Scheduled notifications reference Laravel's `notifications` table. That table
must already exist in the host database (normally from
`ilbronza/notifications`) before Schedules migrations run.

## Host application configuration

The package has no built-in dependency on project models. Publish the config
and declare the models that can receive schedules:

```php
'applicableTo' => [
    \App\Models\Vehicle::class => 'vehicles',
],

'notifications' => [
    // Must be an Eloquent model using Laravel's Notifiable trait.
    'roleModel' => \App\Models\Role::class,
],

'defaultRoles' => [
    'superadmin',
    'administrator',
    'schedules',
],

// Optional per-route overrides for the schedules.roles middleware.
'routeRoles' => [
    // 'ibSchedules.types.index' => ['schedules'],
],
```

Every class listed in `applicableTo` must implement the `Schedulable` contract.
`Type.models` entries must use those exact FQCNs, include `source` and/or
`method`, and resolve to an allowlisted getter — invalid entries are rejected
on model save and on HTTP create/update (422).

The package trait `InteractsWithSchedule` provides the morph relations and the
allowlisted value getters:

```php
use IlBronza\Schedules\Contracts\Schedulable;
use IlBronza\Schedules\Traits\InteractsWithSchedule;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model implements Schedulable
{
    use InteractsWithSchedule;

    protected array $allowedScheduleValueMethods = [
        'getOdometer',
    ];

    public function getOdometer(): float
    {
        return (float) $this->odometer;
    }
}
```

If AccountManager is installed, its configured role model is used as a
backward-compatible fallback when `notifications.roleModel` is null.
FileCabinet's Dossierrow integration is detected only when that package is
installed.

Configuration is validated when the application boots. Invalid model classes
fail immediately instead of being ignored.

## Roles and HTTP access

Package UI routes live under `/schedules-management` with middleware
`web`, `auth`, and `schedules.roles`. Allowed Spatie roles come from
`defaultRoles`, with optional `routeRoles` overrides. Missing authentication or
an unauthorized role denies the request.

## Runtime evaluator

The package registers `schedules:evaluate` and schedules it every minute by
default. The host must still run Laravel's scheduler:

```bash
* * * * * php /path/to/artisan schedule:run
```

The evaluator:

1. reads the current value from each schedulable model;
2. dispatches every reached type notification to the roles configured on the
   schedule type;
3. marks the schedule as expired when its deadline is reached.

The execution is fail-fast: invalid values, missing roles, and notification
dispatch errors stop the command.

```bash
php artisan schedules:evaluate
php artisan schedules:evaluate --chunk=250
```

The automatic evaluator can be configured through `schedules.evaluator`:

```php
'evaluator' => [
    'enabled' => true,
    'cron' => '* * * * *',
    'chunkSize' => 100,
    'overlapExpirationMinutes' => 10,
],
```

## Applying schedules

`ScheduleApplicatorHelper` supports schedules starting from the model's current
value, an explicit starting value, or an explicit deadline. Every application
is transactional: the schedule and all its notification thresholds are either
saved together or rolled back.

The `findOrApplicate*` methods reuse the current schedule and update its limits.
Calling them repeatedly does not create duplicate schedules or thresholds.
When a changed limit moves a notification threshold, the threshold is made
pending again so it can be dispatched for the new deadline.

## Threshold repetition and urgency

`TypeNotification` fields are applied by the evaluator:

- `before` is the first repetition (offset before the schedule deadline);
- `last_repetition` is the last allowed repetition (offset before the deadline;
  omit it to repeat until the deadline itself);
- `repeat_every` + `repeat_every_measurement_unit_id` define the interval between
  sends after the first dispatch;
- `urgency` is written into the notification payload (`urgency` / `priority`).

Every send is stored in `schedules__scheduled_notification_dispatches`. The
`notification_id` / `last_dispatched_value` columns on the scheduled
notification keep only the latest send for idempotency and interval math.
Incomplete repetition configuration (one of `repeat_every` /
measurement unit without the other, or `last_repetition` without repetition)
raises an explicit exception and is never persisted. The same constraints are
enforced on HTTP create/update (422).

## Managing schedules

Schedules can be marked as managed individually or in bulk from the schedules
table. The transition is transactional and propagates the same `managed_at`
timestamp to every related scheduled notification. Repeating the action keeps
the original timestamp.

## Soft delete lifecycle

Soft delete semantics are explicit and cascading where the domain requires it:

- deleting a `Schedule` soft-deletes its scheduled notifications; restoring the
  schedule restores those notifications;
- force-deleting a schedule force-deletes related scheduled notifications
  (including already soft-deleted ones);
- a `Type` cannot be deleted while non-deleted schedules still reference it;
  when deletion is allowed, its type notifications cascade;
- a `TypeNotification` cannot be deleted while scheduled notifications still
  reference it.

Invalid delete attempts raise `RuntimeException` instead of being ignored.
