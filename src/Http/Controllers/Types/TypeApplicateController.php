<?php

namespace IlBronza\Schedules\Http\Controllers\Types;

use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use IlBronza\Schedules\Models\Type;
use Illuminate\Http\Request;

class TypeApplicateController extends CrudSchedulesCrudController
{
    use CRUDIndexTrait;

    public $configModelClassName = 'type';
    public $avoidCreateButton = true;

	public $allowedMethods = [
		'index',
	];

    public function getIndexFieldsArray()
    {
        return config('schedules.models.type.fieldsGroupsFiles.applicableModels')::getFieldsGroup();
    }

    public function index(Request $request, Type $type)
    {
        $this->type = $type;

        return $this->_index($request);
    }

    public function getIndexElements()
    {
        return collect(array_map(function($item)
            {
                $placeholder = $item::make();

                $key = $placeholder->getKeyName();
                $placeholder->$key = $item;

                $placeholder->setApplicatingScheduleType($this->type);

                return $placeholder;
            }, $this->type->getAvailableModels()));
    }
}