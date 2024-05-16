<?php

namespace IlBronza\Schedules\Models;

use IlBronza\CRUD\Models\PackagedBaseModel;
use IlBronza\CRUD\Traits\Model\CRUDUseUuidTrait;
use Illuminate\Support\Str;

class SchedulePackageBaseModel extends PackagedBaseModel
{
    use CRUDUseUuidTrait;

    protected $keyType = 'string';

    static $packageConfigPrefix = 'schedules';

    public function getRouteBaseNamePrefix() : string
    {
        return config('schedules.routePrefix');
    }

    static function getModelConfigPrefix() : string
    {
        return static::$modelConfigPrefix ?? Str::camel(class_basename(static::class));
    }

    static function getProjectClassName() : string
    {
        return config('schedules.models.' . static::getModelConfigPrefix() . '.class');
    }

    public function getTable() : string
    {
        return config("schedules.models.{$this->getModelConfigPrefix()}.table");
    }

    static function getByKey(string $id) : static
    {
        return static::getProjectClassName()::find($id);
    }

    
}