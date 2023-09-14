<?php

namespace IlBronza\Schedules\Http\Controllers\TypeNotifications;

use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDDeleteTrait;
use IlBronza\CRUD\Traits\CRUDEditUpdateTrait;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\CRUD\Traits\CRUDPlainIndexTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use IlBronza\CRUD\Traits\CRUDShowTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use IlBronza\Schedules\Models\Type;
use Illuminate\Http\Request;

class TypeNotificationCrudController extends CrudSchedulesCrudController
{
	use CRUDIndexTrait;
	use CRUDPlainIndexTrait;
    use CRUDCreateStoreTrait;
    use CRUDEditUpdateTrait;
    use CRUDShowTrait;
    use CRUDDeleteTrait;

    use CRUDRelationshipTrait;

    public $configModelClassName = 'typeNotification';
    public $avoidCreateButton = true;
    public $saveAndNew = true;

	public $allowedMethods = [
		'index',
        'create',
        'createFromType',
        'store',
        'edit',
        'update',
        'show',
        'destroy'
	];

    public function getModelDefaultParameters() : array
    {
        return [
            'type_id' => $this->type->getKey()
        ];
    }

    public function getCreateUrl()
    {
        return IbRouter::route(app('schedules'), 'types.typeNotifications.create', ['type' => $this->type]);
    }

    public function createFromType(Type $type)
    {
        $this->type = $type;

        return $this->create();
    }

    public function store(Request $request, Type $type)
    {
        $this->type = $type;

        return $this->_store($request);
    }

    public function getStoreModelAction()
    {
        return IbRouter::route(app('schedules'), 'types.typeNotifications.store', ['type' => $this->type]);
    }

    public function show(string $typeNotification)
    {
        $typeNotification = $this->findModel($typeNotification);

        return $this->_show($typeNotification);
    }

    public function edit(string $typeNotification)
    {
        $typeNotification = $this->findModel($typeNotification);

        return $this->_edit($typeNotification);
    }

    public function update(Request $request, string $typeNotification)
    {
        $typeNotification = $this->findModel($typeNotification);

        return $this->_update($request, $typeNotification);
    }

    public function destroy($typeNotification)
    {
        $typeNotification = $this->findModel($typeNotification);

        return $this->_destroy($typeNotification);
    }
}