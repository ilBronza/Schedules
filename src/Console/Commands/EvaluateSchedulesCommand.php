<?php

namespace IlBronza\Schedules\Console\Commands;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Services\ScheduleEvaluator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EvaluateSchedulesCommand extends Command
{
    protected $signature = 'schedules:evaluate {--chunk=100}';

    protected $description = 'Evaluate current schedules and dispatch reached notifications';

    public function handle(ScheduleEvaluator $evaluator): int
    {
        $this->evaluateSchedules($evaluator);

        return self::SUCCESS;
    }

    protected function evaluateSchedules(ScheduleEvaluator $evaluator): void
    {
        $this->getSchedulesQuery()->chunkById(
            $this->getChunkSize(),
            fn (Collection $schedules) => $this->evaluateChunk($schedules, $evaluator)
        );
    }

    protected function getSchedulesQuery(): Builder
    {
        return Schedule::getProjectClassName()::query()
            ->current()
            ->with(['type.measurementUnit', 'schedulable']);
    }

    protected function getChunkSize(): int
    {
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk option must be greater than zero');
        }

        return $chunkSize;
    }

    protected function evaluateChunk(
        Collection $schedules,
        ScheduleEvaluator $evaluator
    ): void {
        foreach ($schedules as $schedule) {
            $evaluator->evaluate($schedule);
        }
    }
}
