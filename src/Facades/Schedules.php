<?php

namespace IlBronza\Schedules\Facades;

use Illuminate\Support\Facades\Facade;

class Schedules extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'schedules';
    }
}
