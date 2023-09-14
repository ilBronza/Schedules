<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Buttons\Button;
use IlBronza\FormField\Casts\JsonFieldCast;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\TypeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ModelCast
{
    public $model;
    public $source;

    public function __construct(array $item)
    {
        $this->model = $item['model'];
        $this->source = $item['source'];
    }

    public function getModel() : string
    {
        return $this->model;
    }
}

class Type extends SchedulePackageBaseModel
{
    static $deletingRelationships = [];

    protected $casts = [
        'models' => JsonFieldCast::class,
        'roles' => JsonFieldCast::class,
    ];

    public function typeNotifications()
    {
        return $this->hasMany(TypeNotification::class)->orderByRaw('CONVERT(`before`, SIGNED) desc');
    }

    public function getTypeNotifications() : Collection
    {
        return $this->typeNotifications;
    }

    public function getTranslatedName()
    {
        return $this->getName();
    }

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::getProjectClassName());
    }

    public function getMeasurementUnit() : MeasurementUnit
    {
        return $this->measurementUnit;
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::getProjectClassName());
    }

    public function getApplicateIndexUrl() : string
    {
        return $this->getKeyedRoute('applicate.index');
    }

    public function getCreateNotificationTypeUrl()
    {
        return route(config('schedules.routePrefix') . 'types.typeNotifications.create', ['type' => $this]);
    }

    public function getCreateNotificationTypeButton() : Button
    {
        return Button::create([
            'href' => $this->getCreateNotificationTypeUrl(),
            'text' => trans('notificationType.create'),
            'icon' => 'location'
        ]);
    }


    public function getApplicateButton() : Button
    {
        return Button::create([
            'href' => $this->getApplicateIndexUrl(),
            'text' => 'schedules::schedule.applicate',
            'icon' => 'calendar'
        ]);
    }

    public function getValidity() : string
    {
        return $this->validity;
    }

    public function getAvailableModels() : array
    {
        return array_map(function($_item)
            {
                $item = new ModelCast($_item);

                return $item->getModel();
            }, $this->models);
    }

    public function getModelsValue()
    {
        return $this->models;
    }

    public function getTargetScopeNameMethodName() : string
    {
        return "scope" . $this->getTargetScopeName();
    }

    public function getTargetScopeName() : string
    {
        return Str::studly($this->getName());
    }

    public function calculateDeadlineValue(mixed $startingValue)
    {
        $measurementUnit = $this->getMeasurementUnit();

        return $measurementUnit->getDeadlineValue(
            $startingValue,
            $this->getValidity()
        );
    }

    public function allowMultiple() : bool
    {
        return !! $this->allow_multiple;
    }

}