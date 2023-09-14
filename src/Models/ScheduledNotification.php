<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;

class ScheduledNotification extends SchedulePackageBaseModel
{
    // Aggiungere i castables qua per gestire i campi della schedule

    public function isManaged() : bool
    {
        return !! $this->managed_at;
    }

    public function isExpired() : bool
    {
        return !! $this->expired_at;
    }

    public function getStatusAttribute()
    {
        if($this->isManaged())
            return static::$managedStatusString;

        if($this->isExpired())
            return static::$expiredStatusString;

        return ('asd');
    }

    public function typeNotification()
    {
        return $this->belongsTo(TypeNotification::getProjectClassName());
    }

    public function getTypeNotification() : TypeNotification
    {
        return $this->typeNotification;
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

	
}