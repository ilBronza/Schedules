<?php

namespace IlBronza\Schedules\Traits;

use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RuntimeException;

trait InteractsWithSchedule
{
    public Type $applicatingSchedule;

    /**
     * @return list<string>
     */
    public function getAllowedScheduleValueMethods(): array
    {
        if (! property_exists($this, 'allowedScheduleValueMethods')) {
            return [];
        }

        return $this->allowedScheduleValueMethods;
    }

    public function getScheduleCurrentValue(string $method): mixed
    {
        if (! in_array($method, $this->getAllowedScheduleValueMethods(), true)) {
            throw new RuntimeException(
                "Schedule value method [{$method}] is not allowlisted on "
                . static::class
            );
        }

        if (! method_exists($this, $method)) {
            throw new RuntimeException(
                "Schedule value method [{$method}] does not exist on "
                . static::class
            );
        }

        return $this->{$method}();
    }

    /**
     * returns name for the model to appear 
     * in the possible schedulable model list
     * for the type application index method
     * 
     * es. Vehicle::getSchedulableModelNameAttribute() : string 'Veicolo'
     **/
    function getSchedulableModelNameAttribute() : string
	{
		return $this->getMorphClass();
	}

    public function getSchedulableModelTableFieldsArray() : array
    {
        return [
            'fields' => [
            ]
        ];
    }

    public function schedules()
    {
        return $this->morphMany(Schedule::getProjectClassName(), 'schedulable');
    }

	public function getSchedules() : Collection
	{
		return $this->schedules;
	}

    public function scheduledNotifications()
    {
        $schedulesTable = config('schedules.models.schedule.table');

        return $this->hasManyThrough(
            ScheduledNotification::getProjectClassName(),
            Schedule::getProjectClassName(),
            'schedulable_id'
        )->where(
            $schedulesTable . '.schedulable_type',
            $this->getMorphClass()
        );
    }

	public function getCurrentSchedulesByTypeQuery(Type $type) : MorphMany
	{
		return $this->schedules()
					->byType($type)
					->current()
					->orderBy('deadline_value', 'DESC');
	}

    public function getCurrentSchedulesByType(Type $type) : Collection
    {
        return $this->getCurrentSchedulesByTypeQuery($type)
                    ->get();
    }

	public function getCurrentScheduleByType(Type $type) : ? Schedule
	{
		return $this->getCurrentSchedulesByTypeQuery($type)
					->first();
	}

    public function getLatestByType(Type $type) : ? Schedule
    {
        return $this->schedules()
                    ->byType($type)
                    ->orderByDesc('deadline_value')
                    ->first();
    }

    public function scheduleTypes()
    {
        $schedulesTable = config('schedules.models.schedule.table');

        return $this->belongsToMany(
            Type::getProjectClassName(),
            $schedulesTable,
            'schedulable_id',
            'type_id'
        )->where(
            $schedulesTable . '.schedulable_type',
            $this->getMorphClass()
        )->whereNull(
            $schedulesTable . '.deleted_at'
        );
    }

    public function getApplicatedScheduleTypes() : Collection
    {
        return $this->scheduleTypes()->distinct()->get();
    }

    public function getApplicatedScheduleTypesList() : Collection
    {
        return $this->getApplicatedScheduleTypes()->pluck('name', 'id');
    }

    public function getApplicateModelIndexUrl()
    {
        return IbRouter::route(app('schedules'), 'types.applicate.classname.index', [
            'type' => $this->getApplicatingScheduleType()->getKey(),
            'alias' => $this->resolveScheduleApplicableAlias(),
        ]);
    }

    protected function resolveScheduleApplicableAlias(): string
    {
        $classname = static::class;
        $matches = [];

        foreach (config('schedules.applicableTo', []) as $configuredClass => $alias) {
            if ($configuredClass === $classname) {
                $matches[] = $alias;
            }
        }

        if ($matches === []) {
            throw new RuntimeException(
                "Schedule applicable alias is not configured for model [{$classname}]"
            );
        }

        if (count($matches) > 1) {
            throw new RuntimeException(
                "Schedule applicable alias configuration is ambiguous for model [{$classname}]"
            );
        }

        $alias = $matches[0];

        if (! is_string($alias) || $alias === '') {
            throw new RuntimeException(
                "Schedule applicable alias is invalid for model [{$classname}]"
            );
        }

        return $alias;
    }

    public function getSchedulableElementsQuery() : Builder
    {
        $query = static::query();

        $type = $this->getApplicatingScheduleType();
        $scopeMethodName = $type->getTargetScopeNameMethodName();
        $scopeName = $type->getTargetScopeName();

        if (! method_exists($this, $scopeMethodName)) {
            throw new RuntimeException(
                'Schedule type ['.$type->getKey().'] requires missing scope method'
                .' ['.$scopeMethodName.'] on model ['.static::class.']'
                .' for field ['.$scopeName.'].'
            );
        }

        return $query->$scopeName();
    }

    public function setApplicatingScheduleType(Type $type)
    {
        $this->applicatingSchedule = $type;
    }

    public function getApplicatingScheduleType() : Type
    {
        return $this->applicatingSchedule;
    }

    public function getSchedulableElementsCount() : int
    {
        return $this->getSchedulableElementsQuery()->count();
    }

    public function getSchedulableElements() : Collection
    {
        return $this->getSchedulableElementsQuery()->get();
    }
}
