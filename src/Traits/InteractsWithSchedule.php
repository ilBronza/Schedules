<?php

namespace IlBronza\Schedules\Traits;

use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithSchedule
{
    public Type $applicatingSchedule;

    /**
     * returns name for the model to appear 
     * in the possible schedulable model list
     * for the type application index method
     * 
     * es. Vehicle::getSchedulableModelNameAttribute() : string 'Veicolo'
     **/
    abstract function getSchedulableModelNameAttribute() : string;

    // /**
    //  * returns an array to populate 
    //  * the possible schedulable elements columns
    //  * in application table
    //  * 
    //  * es. Vehicle::getSchedulableModelTableFieldsArray : array [
    //  * 
    //         'fields' => 
    //         [
    //             'mySelfPrimary' => 'primary',
    //             'mySelfEdit' => 'links.edit',
    //             'mySelfSee' => 'links.see',
    //             'mySelfApplicate' => [
    //                 'type' => 'links.link',
    //                 'function' => 'getApplicateIndexUrl'
    //             ],

    //             'id' => 'flat',
    //             'name' => 'flat',
    //             'description' => 'flat',

    //             'mySelfDelete' => 'links.delete'
    //         ]
    //  * ]
    //  **/
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

    // public function deployments(): HasManyThrough
    // {
    //     return $this->hasManyThrough(
    //         Deployment::class,
    //         Environment::class,
    //         'project_id', // Foreign key on the environments table...
    //         'environment_id', // Foreign key on the deployments table...
    //         'id', // Local key on the projects table...
    //         'id' // Local key on the environments table...
    //     );
    // }

    public function scheduledNotifications()
    {
        return $this->hasManyThrough(
            ScheduledNotification::getProjectClassName(),
            Schedule::getProjectClassName(),
            'schedulable_id'
        );
    }

    public function getCurrentSchedulesByType(Type $type) : Collection
    {
        return $this->schedules()
                    ->byType($type)
                    ->current()
                    ->get();
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
        return $this->belongsToMany(Type::class, config('schedules.models.schedule.table'), 'schedulable_id', 'type_id')
                ->where('schedulable_type', static::class);
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
            'classname' => get_class($this)
        ]);
    }

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

    public function getSchedulableElementsCount() : int
    {
        return $this->getSchedulableElementsQuery()->count();
    }

    public function getSchedulableElements() : Collection
    {
        return $this->getSchedulableElementsQuery()->get();
    }
}