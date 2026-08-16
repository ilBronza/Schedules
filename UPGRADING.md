# Upgrading

## From v2.5.1

1. Update the package with Composer.
2. Publish config if you need the new keys (`evaluator`, `notifications.roleModel`, `validateConfiguration`):

   ```bash
   php artisan vendor:publish --tag=schedules.config
   ```

3. Ensure the Laravel `notifications` table already exists (provided by
   `ilbronza/notifications` / the host app). Scheduled notifications keep a
   foreign key to `notifications.id`.
4. Run migrations. The incremental files under `database/migrations/2026_*`
   upgrade a v2.5.1 schema to the current shape:

   ```bash
   php artisan migrate
   ```

5. Configure host models before boot validation runs:
   - implement `IlBronza\Schedules\Contracts\Schedulable` (use
     `IlBronza\Schedules\Traits\InteractsWithSchedule`);
   - list them in `schedules.applicableTo`;
   - set `schedules.notifications.roleModel` to a Notifiable Eloquent role
     model (or keep AccountManager's role model as fallback).

6. Make sure the host cron runs Laravel's scheduler so the registered
   evaluator job can fire:

   ```bash
   * * * * * php /path/to/artisan schedule:run
   ```

### Breaking / fail-fast behaviour

- Missing or invalid `applicableTo` / role model classes raise exceptions at
  boot (unless `validateConfiguration` is set to `false`).
- Types and type notifications cannot be deleted while non-deleted schedules
  or scheduled notifications still reference them.
- Incomplete repetition configuration on a type notification raises an
  explicit exception during evaluation.
