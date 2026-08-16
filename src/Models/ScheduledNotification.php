<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Schedules\Services\ScheduleSoftDeleteLifecycle;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Threshold notification attached to a schedule.
 *
 * Declared statuses (precedence: managed > expired > dispatched > pending):
 * - pending: not dispatched, not expired, not managed — still eligible for dispatch
 * - dispatched: at least one send recorded (`notification_id` and/or dispatches);
 *   never overwritten to expired when the schedule deadline is reached
 * - expired: schedule deadline reached before this threshold could be sent;
 *   can no longer be dispatched
 * - managed: schedule was marked managed; highest priority, never overwritten to expired
 */
class ScheduledNotification extends SchedulePackageBaseModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_MANAGED = 'managed';

    static $deletingRelationships = [];

    protected $casts = [
        'expired_at' => 'datetime',
        'managed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ScheduledNotification $notification): void {
            app(ScheduleSoftDeleteLifecycle::class)
                ->assertNoSoftDeletedUniqueConflictOnCreate($notification);
        });
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::getProjectClassName());
    }

    public function dispatches()
    {
        return $this->hasMany(
            ScheduledNotificationDispatch::getProjectClassName()
        );
    }

    public function isManaged() : bool
    {
        return ! is_null($this->managed_at);
    }

    public function isExpired() : bool
    {
        return ! is_null($this->expired_at);
    }

    public function isDispatched(): bool
    {
        return ! is_null($this->notification_id)
            || $this->dispatches()->exists();
    }

    public function isPending(): bool
    {
        return ! $this->isManaged()
            && ! $this->isExpired()
            && ! $this->isDispatched();
    }

    public function getStatusAttribute(): string
    {
        if($this->isManaged())
            return self::STATUS_MANAGED;

        if($this->isExpired())
            return self::STATUS_EXPIRED;

        if ($this->isDispatched())
            return self::STATUS_DISPATCHED;

        return self::STATUS_PENDING;
    }

    public function typeNotification()
    {
        return $this->belongsTo(TypeNotification::getProjectClassName());
    }

    public function getTypeNotification() : TypeNotification
    {
        $typeNotification = $this->typeNotification;

        if (is_null($typeNotification)) {
            throw new RuntimeException(
                'ScheduledNotification ['.$this->getKey().'] is missing required type_notification_id relation.'
            );
        }

        return $typeNotification;
    }

    public function getOptionalTypeNotification(): ?TypeNotification
    {
        if (is_null($this->type_notification_id)) {
            return null;
        }

        return $this->getTypeNotification();
    }

    public function getNameAttribute()
    {
        return $this->getTypeNotification()->getName();
    }

    public function setDeadlineValue(mixed $value = null, bool $save = false)
    {
    	return $this->_customSetter('deadline_value', $value, $save);
    }

    public function setTypeNotificationId(string $value, bool $save = false)
    {
    	return $this->_customSetter('type_notification_id', $value, $save);
    }

    public function getDeadlineValue(Schedule $schedule): mixed
    {
        return $schedule->parseOutputValueByType($this->deadline_value);
    }

    public function markDispatched(string $notificationId, mixed $currentValue): void
    {
        $this->dispatches()->create([
            'notification_id' => $notificationId,
            'current_value' => $currentValue,
            'dispatched_at' => Carbon::now(),
        ]);

        $this->notification_id = $notificationId;
        $this->last_dispatched_value = $currentValue;
        $this->save();
    }

    public function getLastDispatchedValue(Schedule $schedule): mixed
    {
        if (is_null($this->last_dispatched_value)) {
            return null;
        }

        return $schedule->parseOutputValueByType($this->last_dispatched_value);
    }

    public function markExpired(): void
    {
        if (! $this->isPending()) {
            throw new RuntimeException(
                'ScheduledNotification ['.$this->getKey()
                .'] cannot be expired because its status is ['.$this->status.'].'
            );
        }

        $this->expired_at = Carbon::now();
        $this->save();
    }

    public function latestDispatch(): ?ScheduledNotificationDispatch
    {
        return $this->dispatches()
            ->orderByDesc('dispatched_at')
            ->orderByDesc('created_at')
            ->first();
    }

    public function reschedule(mixed $deadlineValue): void
    {
        $this->dispatches()->delete();
        $this->deadline_value = $deadlineValue;
        $this->notification_id = null;
        $this->last_dispatched_value = null;
        $this->expired_at = null;
        $this->managed_at = null;
        $this->save();
    }

    public function scopePending($query)
    {
        return $query
            ->whereNull('expired_at')
            ->whereNull('managed_at')
            ->where(function ($pendingQuery): void {
                $pendingQuery
                    ->whereNull('notification_id')
                    ->orWhereHas('typeNotification', function ($typeNotificationQuery): void {
                        $typeNotificationQuery
                            ->whereNotNull('repeat_every')
                            ->whereNotNull('repeat_every_measurement_unit_id');
                    });
            });
    }
}
