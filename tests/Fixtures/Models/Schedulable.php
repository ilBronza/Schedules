<?php

namespace IlBronza\Schedules\Tests\Fixtures\Models;

use IlBronza\Schedules\Contracts\Schedulable as SchedulableContract;
use IlBronza\Schedules\Traits\InteractsWithSchedule;
use Illuminate\Database\Eloquent\Model;

class Schedulable extends Model implements SchedulableContract
{
    use InteractsWithSchedule;

    protected $table = 'schedulables';

    protected $guarded = [];

    protected array $allowedScheduleValueMethods = [
        'getCurrentReading',
        'getAlternateReading',
    ];

    public function getCurrentReading(): float
    {
        return (float) $this->current_reading;
    }

    public function getAlternateReading(): float
    {
        return $this->getCurrentReading() + 10;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
