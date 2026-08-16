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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RuntimeException;

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

    protected Type $parentType;

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
            'type_id' => $this->parentType->getKey(),
        ];
    }

    public function getCreateUrl()
    {
        return IbRouter::route(app('schedules'), 'types.typeNotifications.create', [
            'type' => $this->parentType->getKey(),
        ]);
    }

    public function createFromType(string $type)
    {
        $this->parentType = $this->resolveParentType($type);

        return $this->create();
    }

    public function store(Request $request, string $type)
    {
        $this->parentType = $this->resolveParentType($type);

        return $this->_store($request);
    }

    public function getStoreModelAction()
    {
        return IbRouter::route(app('schedules'), 'types.typeNotifications.store', [
            'type' => $this->parentType->getKey(),
        ]);
    }

    public function makeModel() : Model
    {
        $model = parent::makeModel();

        $model->type_id = (string) $this->parentType->getKey();

        if ($model->type_id === '') {
            throw new RuntimeException(
                'TypeNotification requires type_id from the parent Type route.'
            );
        }

        return $model;
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

        $request->validate([
            'type_id' => 'prohibited',
        ]);

        return $this->_update($request, $typeNotification);
    }

    public function destroy($typeNotification)
    {
        $typeNotification = $this->findModel($typeNotification);

        return $this->_destroy($typeNotification);
    }

    protected function resolveParentType(string $typeKey): Type
    {
        $type = Type::getProjectClassName()::query()->find($typeKey);

        if (is_null($type)) {
            throw new RuntimeException(
                "Type [{$typeKey}] not found for type notification route."
            );
        }

        return $type;
    }
}
