<?php

namespace IlBronza\Schedules;

use IlBronza\Schedules\Console\Commands\EvaluateSchedulesCommand;
use IlBronza\Schedules\Console\Commands\InvalidateSchedulesCommand;
use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Http\Middleware\SchedulesMiddlewareRolesPermissions;
use IlBronza\Schedules\Services\IlBronzaScheduleNotificationDispatcher;
use IlBronza\Schedules\Services\SchedulesConfigurationValidator;
use IlBronza\Schedules\Services\TypeModelsConfigurationValidator;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class SchedulesServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->validateConfiguration();
        $this->registerTypeModelsValidationRule();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'schedules');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'ilbronza');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/schedules.php');

        $this->app['router']->aliasMiddleware('schedules.roles', SchedulesMiddlewareRolesPermissions::class);

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function registerTypeModelsValidationRule(): void
    {
        Validator::extend(
            'type_models_configuration',
            function (
                string $attribute,
                mixed $value,
                array $parameters,
                \Illuminate\Validation\Validator $validator
            ): bool {
                try {
                    $this->app
                        ->make(TypeModelsConfigurationValidator::class)
                        ->validate($value);

                    return true;
                } catch (RuntimeException $exception) {
                    $validator->addReplacer(
                        'type_models_configuration',
                        fn (): string => $exception->getMessage()
                    );

                    return false;
                }
            }
        );
    }

    protected function validateConfiguration(): void
    {
        if (! config('schedules.validateConfiguration', true)) {
            return;
        }

        $this->app
            ->make(SchedulesConfigurationValidator::class)
            ->validate();
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerConfiguration();

        $this->registerRuntimeServices();

        $this->app->singleton('schedules', function ($app) {
            return new Schedules;
        });
    }

    protected function registerConfiguration(): void
    {
        $configPath = __DIR__.'/../config/schedules.php';

        $this->mergeConfigFrom($configPath, 'schedules');

        $packageConfig = require $configPath;
        $projectConfig = config('schedules', []);

        config([
            'schedules' => array_replace_recursive(
                $packageConfig,
                $projectConfig
            ),
        ]);
    }

    protected function registerRuntimeServices(): void
    {
        $this->app->bind(
            ScheduleNotificationDispatcher::class,
            IlBronzaScheduleNotificationDispatcher::class
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['schedules'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__.'/../config/schedules.php' => config_path('schedules.php'),
        ], 'schedules.config');

        $this->commands([
            EvaluateSchedulesCommand::class,
            InvalidateSchedulesCommand::class,
        ]);

        $this->registerEvaluatorSchedule();
    }

    protected function registerEvaluatorSchedule(): void
    {
        if (! config('schedules.evaluator.enabled', true)) {
            return;
        }

        $this->callAfterResolving(
            ConsoleSchedule::class,
            fn (ConsoleSchedule $schedule) => $this->scheduleEvaluator($schedule)
        );
    }

    protected function scheduleEvaluator(ConsoleSchedule $schedule): void
    {
        $schedule
            ->command($this->getEvaluatorCommand())
            ->cron(config('schedules.evaluator.cron', '* * * * *'))
            ->withoutOverlapping(
                config('schedules.evaluator.overlapExpirationMinutes', 10)
            );
    }

    protected function getEvaluatorCommand(): string
    {
        $chunkSize = config('schedules.evaluator.chunkSize', 100);

        return "schedules:evaluate --chunk={$chunkSize}";
    }
}
