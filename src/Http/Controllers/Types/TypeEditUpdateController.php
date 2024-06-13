<?php

namespace IlBronza\Schedules\Http\Controllers\Types;

use IlBronza\CRUD\Http\Controllers\Traits\PackageStandardEditUpdateTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use Illuminate\Http\Request;

class TypeEditUpdateController extends CrudSchedulesCrudController
{
    use PackageStandardEditUpdateTrait;

    public $allowedMethods = [
        'edit',
        'update',
    ];

    public $configModelClassName = 'type';
}