<?php

namespace IlBronza\Schedules\Tests\Fixtures\Models\Alpha;

use IlBronza\Schedules\Contracts\Schedulable as SchedulableContract;
use IlBronza\Schedules\Traits\InteractsWithSchedule;
use Illuminate\Database\Eloquent\Model;

class CollidingSchedulable extends Model implements SchedulableContract
{
    use InteractsWithSchedule;

    protected $table = 'schedulables';

    protected $guarded = [];

    protected array $allowedScheduleValueMethods = [
        'getCurrentReading',
    ];

    public function getCurrentReading(): float
    {
        return (float) $this->current_reading;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
