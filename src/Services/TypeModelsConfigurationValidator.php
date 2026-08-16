<?php

namespace IlBronza\Schedules\Services;

use IlBronza\Schedules\Contracts\Schedulable;
use Illuminate\Support\Str;
use RuntimeException;

class TypeModelsConfigurationValidator
{
    /**
     * @param  mixed  $models
     */
    public function validate(mixed $models, mixed $typeId = null): void
    {
        $context = $this->typeContext($typeId);

        if (! is_array($models)) {
            throw new RuntimeException(
                "{$context} models must be an array of configuration entries."
            );
        }

        if ($models === []) {
            throw new RuntimeException(
                "{$context} models must contain at least one configuration entry."
            );
        }

        foreach ($models as $index => $entry) {
            $this->validateEntry($entry, $index, $context);
        }
    }

    /**
     * Resolve the schedule value getter the same way the applicator does.
     *
     * Explicit method wins; otherwise source becomes get{StudlySource}.
     */
    public function resolveValueGetterMethod(array $modelParameters): string
    {
        if ($this->filledString($modelParameters['method'] ?? null)) {
            return (string) $modelParameters['method'];
        }

        if ($this->filledString($modelParameters['source'] ?? null)) {
            return $this->getterFromSource((string) $modelParameters['source']);
        }

        throw new RuntimeException(
            'I can\'t calculate the current value for the module\'s schedule. '
            . 'You need to declare a source field or a method name'
        );
    }

    public function getterFromSource(string $source): string
    {
        return 'get' . Str::studly($source);
    }

    protected function validateEntry(
        mixed $entry,
        int|string $index,
        string $context
    ): void {
        if (! is_array($entry)) {
            throw new RuntimeException(
                "{$context} models[{$index}] must be an array."
            );
        }

        $modelClass = $entry['model'] ?? null;

        if (! $this->filledString($modelClass)) {
            throw new RuntimeException(
                "{$context} models[{$index}] is missing required model class."
            );
        }

        $modelClass = (string) $modelClass;
        $applicableTo = config('schedules.applicableTo', []);

        if (! array_key_exists($modelClass, $applicableTo)) {
            throw new RuntimeException(
                "{$context} models[{$index}] model [{$modelClass}] is not"
                . ' configured in schedules.applicableTo.'
            );
        }

        if (! class_exists($modelClass) || ! is_a($modelClass, Schedulable::class, true)) {
            throw new RuntimeException(
                "{$context} models[{$index}] model [{$modelClass}] must implement "
                . Schedulable::class
            );
        }

        $hasMethod = $this->filledString($entry['method'] ?? null);
        $hasSource = $this->filledString($entry['source'] ?? null);

        if (! $hasMethod && ! $hasSource) {
            throw new RuntimeException(
                "{$context} models[{$index}] requires at least one of source or method."
            );
        }

        /** @var Schedulable $instance */
        $instance = new $modelClass;
        $allowed = $instance->getAllowedScheduleValueMethods();

        if ($hasMethod) {
            $method = (string) $entry['method'];

            if (! in_array($method, $allowed, true)) {
                throw new RuntimeException(
                    "{$context} models[{$index}] method [{$method}] is not allowlisted on "
                    . $modelClass
                );
            }
        }

        if ($hasSource) {
            $getter = $this->getterFromSource((string) $entry['source']);

            if (! in_array($getter, $allowed, true)) {
                throw new RuntimeException(
                    "{$context} models[{$index}] source [{$entry['source']}]"
                    . " resolves to [{$getter}] which is not allowlisted on "
                    . $modelClass
                );
            }
        }
    }

    protected function typeContext(mixed $typeId): string
    {
        if ($typeId === null || $typeId === '') {
            return 'Schedule type';
        }

        return 'Schedule type ['.$typeId.']';
    }

    protected function filledString(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
