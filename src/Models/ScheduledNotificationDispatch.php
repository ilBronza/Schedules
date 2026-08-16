<?php

namespace IlBronza\Schedules\Models;

use IlBronza\CRUD\Traits\Model\CRUDUseUuidTrait;
use Illuminate\Database\Eloquent\Model;

class ScheduledNotificationDispatch extends Model
{
    use CRUDUseUuidTrait;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('schedules.models.scheduledNotificationDispatch.table');
    }

    public function scheduledNotification()
    {
        return $this->belongsTo(ScheduledNotification::getProjectClassName());
    }

    public static function getProjectClassName(): string
    {
        return config('schedules.models.scheduledNotificationDispatch.class');
    }
}
