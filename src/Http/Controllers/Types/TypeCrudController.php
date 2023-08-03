<?php

namespace IlBronza\Schedules\Http\Controllers\Types;

use IlBronza\CRUD\Traits\CRUDCreateStoreTrait;
use IlBronza\CRUD\Traits\CRUDDeleteTrait;
use IlBronza\CRUD\Traits\CRUDEditUpdateTrait;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\CRUD\Traits\CRUDPlainIndexTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use IlBronza\CRUD\Traits\CRUDShowTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use Illuminate\Http\Request;

class TypeCrudController extends CrudSchedulesCrudController
{
	use CRUDIndexTrait;
	use CRUDPlainIndexTrait;
    use CRUDCreateStoreTrait;
    use CRUDEditUpdateTrait;
    use CRUDShowTrait;
    use CRUDDeleteTrait;

    use CRUDRelationshipTrait;

    public $configModelClassName = 'type';

	public $allowedMethods = [
		'index',
        'create',
        'store',
        'edit',
        'update',
        'show',
        'destroy'
	];

    public function getGenericParametersFile() : ? string
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.parametersFiles.create");
    }

    public function getEditParametersFile() : ? string
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.parametersFiles.edit");
    }

    public function getIndexFieldsArray()
    {
        return config($this->getBaseConfigName() . ".models.$this->configModelClassName.fieldsGroupsFiles.index")::getFieldsGroup();
    }

    public function getIndexElements()
    {
        return $this->getModelClass()::all();
    }

    public function getRelationshipsManagerClass()
    {
        return config($this->getBaseConfigName() . ".models.{$this->configModelClassName}.relationshipsManagerClasses.show");
    }

    public function show(string $type)
    {
        $type = $this->findModel($type);

        return $this->_show($type);
    }

    public function edit(string $type)
    {
        $type = $this->findModel($type);

        return $this->_edit($type);
    }

    public function update(Request $request, string $type)
    {
        $type = $this->findModel($type);

        return $this->_update($request, $type);
    }

    public function destroy($vehicle)
    {
        $vehicle = $this->findModel($vehicle);

        return $this->_destroy($vehicle);
    }
}