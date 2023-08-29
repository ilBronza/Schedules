<?php

namespace IlBronza\Schedules\Models;

use IlBronza\CRUD\Models\BaseModel;
use IlBronza\CRUD\Traits\Model\CRUDUseUuidTrait;
use Illuminate\Support\Str;

class SchedulePackageBaseModel extends BaseModel
{
    use CRUDUseUuidTrait;

    protected $keyType = 'string';

    public function getRouteBaseNamePrefix() : ? string
    {
        return config('schedules.routePrefix');
    }

    static function getModelConfigPrefix()
    {
        return static::$modelConfigPrefix ?? Str::camel(class_basename(static::class));
    }

    static function getProjectClassName()
    {
        return config('schedules.models.' . static::getModelConfigPrefix() . '.class');
    }

    public function getTable()
    {
        return config("schedules.models.{$this->getModelConfigPrefix()}.table");
    }

    static function getByKey(string $id) : static
    {
        return static::getProjectClassName()::find($id);
    }

    
}