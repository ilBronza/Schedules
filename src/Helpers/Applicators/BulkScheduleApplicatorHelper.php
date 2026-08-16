<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkScheduleApplicatorHelper
{
    protected Collection $scheduleTypes;
    protected Collection $models;

    public function __construct()
    {
        $this->scheduleTypes = collect();
        $this->models = collect();
    }

    public static function applicateScheduleToModels(
        Type $type,
        Collection $models
    ): Collection {
        $helper = new static();
        $helper->addScheduleType($type);
        $helper->addModels($models);

        return $helper->applicateBulk();
    }

    public function addScheduleType(Type $type): void
    {
        $this->scheduleTypes->push($type);
    }

    public function addModels(Collection $models): void
    {
        $models->each(
            fn (Model $model) => $this->addModel($model)
        );
    }

    public function addModel(Model $model): void
    {
        $this->models->push($model);
    }

    public function getScheduleTypes(): Collection
    {
        return $this->scheduleTypes;
    }

    public function getModels(): Collection
    {
        return $this->models;
    }

    public function applicateBulk(): Collection
    {
        return DB::transaction(function (): Collection {
            $types = $this->lockTypes(
                $this->orderedScheduleTypes()
            );
            $models = $this->lockModels(
                $this->orderedModels()
            );

            return $types->flatMap(
                fn (Type $type): Collection => $models->map(
                    fn (Model $model): Schedule => $this
                        ->applicateScheduleToModel($type, $model)
                )
            );
        });
    }

    protected function orderedScheduleTypes(): Collection
    {
        return $this->getScheduleTypes()
            ->sortBy(fn (Type $type) => $type->getKey())
            ->values();
    }

    protected function orderedModels(): Collection
    {
        return $this->getModels()
            ->sortBy(fn (Model $model) => [
                $model->getMorphClass(),
                $model->getKey(),
            ])
            ->values();
    }

    protected function lockTypes(Collection $types): Collection
    {
        return $types->map(
            fn (Type $type): Type => $this->lockType($type)
        );
    }

    protected function lockModels(Collection $models): Collection
    {
        return $models->map(
            fn (Model $model): Model => $this->lockModel($model)
        );
    }

    protected function lockType(Type $type): Type
    {
        return $type
            ->newQuery()
            ->lockForUpdate()
            ->findOrFail($type->getKey());
    }

    protected function lockModel(Model $model): Model
    {
        return $model
            ->newQuery()
            ->lockForUpdate()
            ->findOrFail($model->getKey());
    }

    protected function applicateScheduleToModel(
        Type $type,
        Model $model
    ): Schedule {
        return ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $model
        );
    }
}
