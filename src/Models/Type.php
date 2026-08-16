<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Buttons\Button;
use IlBronza\FormField\Casts\JsonFieldCast;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Services\ScheduleSoftDeleteLifecycle;
use IlBronza\Schedules\Services\TypeModelsConfigurationValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

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
    static $deletingRelationships = ['typeNotifications'];

    protected $casts = [
        'models' => JsonFieldCast::class,
        'roles' => JsonFieldCast::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Type $type): void {
            $type->assertModelsConfigurationIsValid();
        });

        static::deleting(function (Type $type): void {
            $lifecycle = app(ScheduleSoftDeleteLifecycle::class);

            $lifecycle->assertTypeCanBeDeleted($type);

            if ($type->isForceDeleting()) {
                $lifecycle->cascadeForceDeleteTypeNotifications($type);

                return;
            }

            $lifecycle->cascadeSoftDeleteTypeNotifications($type);
        });

        static::restored(function (Type $type): void {
            app(ScheduleSoftDeleteLifecycle::class)
                ->cascadeRestoreTypeNotifications($type);
        });
    }

    public function assertModelsConfigurationIsValid(): void
    {
        app(TypeModelsConfigurationValidator::class)->validate(
            $this->models,
            $this->getKey()
        );
    }

    public function typeNotifications()
    {
        return $this->hasMany(TypeNotification::getProjectClassName());
    }

    public function getTypeNotifications() : Collection
    {
        return $this->typeNotifications
            ->sortByDesc(
                fn (TypeNotification $notification): float => (float) $notification->getBefore()
            )
            ->values();
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
        if($this->relationLoaded('measurementUnit'))
            return $this->measurementUnit;

        return MeasurementUnit::getProjectClassName()::findCached($this->measurement_unit_id);
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
            'text' => 'schedules::notificationType.create',
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

    public function getValidity() : ? string
    {
        return $this->validity;
    }

    public function getPercentageValidityLimit(): float
    {
        return (float) ($this->percentage_validity ?? 99);
    }

    public function getNotificationRoleIds(): Collection
    {
        return collect($this->roles)
            ->map(fn (mixed $role) => $this->extractNotificationRoleId($role))
            ->unique()
            ->values();
    }

    protected function extractNotificationRoleId(mixed $role): mixed
    {
        if (! is_array($role)) {
            if ($role === null || $role === '') {
                throw new RuntimeException(
                    'Schedule type ['.$this->getKey().'] has malformed roles entry'
                    .' on field [roles]: '.json_encode($role)
                );
            }

            return $role;
        }

        if (array_key_exists('roles', $role)) {
            $roleId = $role['roles'];
        } elseif (array_key_exists('role_id', $role)) {
            $roleId = $role['role_id'];
        } elseif (array_key_exists('id', $role)) {
            $roleId = $role['id'];
        } else {
            throw new RuntimeException(
                'Schedule type ['.$this->getKey().'] has malformed roles entry'
                .' on field [roles]: '.json_encode($role)
            );
        }

        if ($roleId === null || $roleId === '') {
            throw new RuntimeException(
                'Schedule type ['.$this->getKey().'] has malformed roles entry'
                .' on field [roles]: '.json_encode($role)
            );
        }

        return $roleId;
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

    public function allowMultiple() : bool
    {
        return !! $this->allow_multiple;
    }

}