<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Buttons\Button;
use IlBronza\FormField\Casts\JsonFieldCast;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;
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
        'notifications' => JsonFieldCast::class
    ];

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

    public function getApplicateButton() : Button
    {
        return Button::create([
            'href' => $this->getApplicateIndexUrl(),
            'text' => 'schedules::schedule.applicate',
            'icon' => 'calendar'
        ]);
    }

    // public function getModels() : array
    // {
    //     return $this->models;
    // }

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

        $measurementUnitHelper = $measurementUnit->getHelper();

        die("pescare unità di misura, trovare la conversione con l'helper, chiamare l'helper per fare la somma o eventuale cosa. Salut!");

        dd($measurementUnit);

        dd($this);

        dd($measurementUnitHelper);
        dd($this);
    }
}