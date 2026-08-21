<?php

namespace IlBronza\Schedules\Console\Commands;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\ScheduleInvalidator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class InvalidateSchedulesCommand extends Command
{
    protected $signature = 'schedules:invalidate
        {type? : Schedule Type ID}
        {--all : Invalidate schedules of every Schedule Type}
        {--dry-run : Report schedules without invalidating them}
        {--chunk=500 : Number of schedules processed at a time}';

    protected $description = 'Invalidate schedules of one or every Schedule Type';

    protected int $found = 0;
    protected int $invalidated = 0;
    protected int $errors = 0;

    public function handle(ScheduleInvalidator $invalidator): int
    {
        $this->resetSummary();

        $chunkSize = $this->getChunkSize();
        $type = $this->resolveType();
        $query = $this->schedulesQuery($type);
        $this->found = (clone $query)->count();

        if (! $this->option('dry-run')) {
            $query->chunkById(
                $chunkSize,
                fn (Collection $schedules) => $this->invalidateChunk(
                    $schedules,
                    $invalidator
                )
            );
        }

        $this->renderSummary($type);

        return self::SUCCESS;
    }

    protected function resolveType(): ?Type
    {
        $all = (bool) $this->option('all');
        $typeId = $this->argument('type');

        if ($all && ! is_null($typeId)) {
            throw new InvalidArgumentException(
                'The type argument and --all option cannot be used together'
            );
        }

        if ($all) {
            return null;
        }

        if (is_null($typeId)) {
            throw new InvalidArgumentException(
                'Either a Schedule Type ID or the --all option is required'
            );
        }

        $typeClass = Type::getProjectClassName();
        $typeId = (string) $typeId;
        $type = $typeClass::query()->find($typeId);

        if ($type) {
            return $type;
        }

        throw new InvalidArgumentException("Schedule Type [{$typeId}] does not exist");
    }

    protected function schedulesQuery(?Type $type): Builder
    {
        $scheduleClass = Schedule::getProjectClassName();

        return $scheduleClass::query()
            ->when(
                $type,
                fn (Builder $query, Type $type) => $query->where(
                    'type_id',
                    $type->getKey()
                )
            )
            ->orderBy((new $scheduleClass())->qualifyColumn('id'));
    }

    protected function resetSummary(): void
    {
        $this->found = 0;
        $this->invalidated = 0;
        $this->errors = 0;
    }

    protected function invalidateChunk(
        Collection $schedules,
        ScheduleInvalidator $invalidator
    ): void {
        foreach ($schedules as $schedule) {
            try {
                if ($invalidator->invalidate($schedule)) {
                    $this->invalidated++;
                }
            } catch (Throwable $exception) {
                $this->errors++;
                $this->error(
                    'Schedule ['.$schedule->getKey().']: '.$exception->getMessage()
                );
            }
        }
    }

    protected function getChunkSize(): int
    {
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk option must be greater than zero');
        }

        return $chunkSize;
    }

    protected function renderSummary(?Type $type): void
    {
        $this->newLine();
        $this->info($this->option('dry-run') ? 'Dry-run summary' : 'Invalidation summary');
        $this->line(
            $type
                ? 'Schedule Type: '.$type->getName().' ['.$type->getKey().']'
                : 'Schedule Types: all'
        );
        $this->line('Schedules found: '.$this->found);
        $this->line('Schedules invalidated: '.$this->invalidated);
        $this->line('Errors: '.$this->errors);
    }
}
