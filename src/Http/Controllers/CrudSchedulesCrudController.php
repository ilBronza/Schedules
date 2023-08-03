<?php

namespace IlBronza\Schedules\Http\Controllers;

use IlBronza\CRUD\CRUD;

class CrudSchedulesCrudController extends CRUD
{
    public function getBaseConfigName()
    {
        return 'schedules';
    }

    public function getRouteBaseNamePrefix() : ? string
    {
        return config($this->getBaseConfigName() . ".routePrefix");
    }

    public function setModelClass()
    {
        $this->modelClass = config($this->getBaseConfigName() . ".models.{$this->configModelClassName}.class");
    }
}