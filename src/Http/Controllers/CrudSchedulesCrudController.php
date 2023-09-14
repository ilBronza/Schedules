<?php

namespace IlBronza\Schedules\Http\Controllers;

use IlBronza\CRUD\CRUD;
use IlBronza\CRUD\Traits\CRUDShowTrait;

class CrudSchedulesCrudController extends CRUD
{
    use CRUDShowTrait;

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

    public function getModelClass() : string
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.class");
    }

    public function getGenericParametersFile() : ? string
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.parametersFiles.create");
    }

    public function getIndexFieldsArray()
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.fieldsGroupsFiles.index")::getFieldsGroup();
    }

    public function getRelatedFieldsArray()
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.fieldsGroupsFiles.related")::getFieldsGroup();
    }

    public function getIndexElements()
    {
        return $this->getModelClass()::all();
    }

    public function getRelationshipsManagerClass()
    {
        return config($this->getBaseConfigName() . ".models.{$this->configModelClassName}.relationshipsManagerClasses.show");
    }

}