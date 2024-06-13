<?php

namespace IlBronza\Schedules\Http\Controllers\Types;

use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDDeleteTrait;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\CRUD\Traits\CRUDPlainIndexTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use Illuminate\Http\Request;

class TypeCrudController extends CrudSchedulesCrudController
{
	use CRUDIndexTrait;
	use CRUDPlainIndexTrait;
    use CRUDCreateStoreTrait;
    use CRUDDeleteTrait;

    use CRUDRelationshipTrait;

    public $configModelClassName = 'type';

	public $allowedMethods = [
		'index',
        'create',
        'store',
        'show',
        'destroy'
	];

    public function setShowButtons()
    {
        $this->showButtons[] = $this->getModel()->getCreateNotificationTypeButton();

        $this->showButtons[] = $this->getModel()->getApplicateButton();
    }

    public function show(string $type)
    {
        $type = $this->findModel($type);

        return $this->_show($type);
    }

    public function destroy($vehicle)
    {
        $vehicle = $this->findModel($vehicle);

        return $this->_destroy($vehicle);
    }
}