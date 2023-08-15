<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Buttons\Button;
use IlBronza\FormField\Casts\JsonFieldCast;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
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

    public function measurementUnit()
    {
    	return $this->belongsTo(MeasurementUnit::getProjectClassName());
    }

    public function getApplicateUrl() : string
    {
        return $this->getKeyedRoute('applicate.index');
    }

    public function getApplicateButton() : Button
    {
        return Button::create([
            'href' => $this->getApplicateUrl(),
            'text' => 'schedules::schedule.applicate',
            'icon' => 'calendar'
        ]);
    }

    public function getModels() : array
    {
        return $this->models;
    }

    public function getAvailableModels() : array
    {
        return array_map(function($_item)
            {
                $item = new ModelCast($_item);

                return $item->getModel();
            }, $this->getModels());
    }

    public function getTargetScopeNameMethodName() : string
    {
        return "scope" . $this->getTargetScopeName();
    }

    public function getTargetScopeName() : string
    {
        return Str::studly($this->getName());
    }
}