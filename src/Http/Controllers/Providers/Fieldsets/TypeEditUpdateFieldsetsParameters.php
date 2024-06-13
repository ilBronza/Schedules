<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;
use Spatie\Permission\Models\Role;

class TypeEditUpdateFieldsetsParameters extends TypeCreateStoreFieldsetsParameters
{
    public function getRolesArray() : array
    {
        return Role::all()->pluck('name', 'id')->toArray();
    }

    public function getModelsArray() : array
    {
        return config('schedules.applicableTo');
    }

    public function _getFieldsetsParameters() : array
    {
        $result = parent::_getFieldsetsParameters();

        $result['applications']['fields']['models']['fields']['source'] = ['text' => 'string|nullable|max:255']; 

        $result['applications']['fields']['models']['fields']['method'] = ['text' => 'string|nullable|max:255'];

        return $result;
    }
}
