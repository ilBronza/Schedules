<?php

namespace IlBronza\Schedules\Http\Controllers\Schedules;

use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDDeleteTrait;
use IlBronza\CRUD\Traits\CRUDEditUpdateTrait;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\CRUD\Traits\CRUDPlainIndexTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use IlBronza\CRUD\Traits\CRUDShowTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use Illuminate\Http\Request;

class ScheduleCrudController extends CrudSchedulesCrudController
{
	use CRUDIndexTrait;
	use CRUDPlainIndexTrait;
    use CRUDCreateStoreTrait;
    use CRUDEditUpdateTrait;
    use CRUDShowTrait;
    use CRUDDeleteTrait;

    use CRUDRelationshipTrait;

    public $configModelClassName = 'schedule';
    public $avoidCreateButton = true;
    public $saveAndNew = true;

	public $allowedMethods = [
		'index',
        'create',
        'store',
        'edit',
        'update',
        'show',
        'destroy'
	];

    public function show(string $schedule)
    {
        $schedule = $this->findModel($schedule);

        return $this->_show($schedule);
    }

    public function edit(string $schedule)
    {
        $schedule = $this->findModel($schedule);

        return $this->_edit($schedule);
    }

    public function update(Request $request, string $schedule)
    {
        $schedule = $this->findModel($schedule);

        return $this->_update($request, $schedule);
    }

    public function destroy($schedule)
    {
        $schedule = $this->findModel($schedule);

        return $this->_destroy($schedule);
    }
}