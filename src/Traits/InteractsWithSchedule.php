<?php

namespace IlBronza\Schedules\Traits;

use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithSchedule
{
    public Type $applicatingSchedule;

    abstract function getSchedulableModelNameAttribute() : string;

    public function getSchedulableElementsQuery() : Builder
    {
        $query = static::query();

        $scopeMethodName = $this->getApplicatingScheduleType()->getTargetScopeNameMethodName();

        $scopeName = $this->getApplicatingScheduleType()->getTargetScopeName();

        if(method_exists($this, $scopeMethodName))
            return $query->$scopeName();

        return $query;
    }

    public function setApplicatingScheduleType(Type $type)
    {
        $this->applicatingSchedule = $type;
    }

    public function getApplicatingScheduleType() : Type
    {
        return $this->applicatingSchedule;
    }

    public function getSchedulableElementsCount() : Builder
    {
        return $this->getSchedulableElementsQuery()->count();
    }

    public function getSchedulableElements() : Collection
    {
        return $this->getSchedulableElementsQuery()->get();
    }
}