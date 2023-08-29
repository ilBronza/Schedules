<?php

namespace IlBronza\Schedules\Http\Controllers\Types;

use IlBronza\Buttons\Button;
use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\Schedules\Helpers\BulkScheduleApplicatorHelper;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use IlBronza\Schedules\Models\Type;
use Illuminate\Http\Request;

class TypeApplicateClassnameController extends CrudSchedulesCrudController
{
    use CRUDIndexTrait;

    public $configModelClassName = 'type';
    public $avoidCreateButton = true;
    public $selectRow = true;

	public $allowedMethods = [
		'index',
        'store'
	];

    public function addIndexButtons()
    {
        $button = Button::create([
                'href' => IbRouter::route(
                    app('schedules'),
                    'types.applicate.classname.store',
                    [
                        'type' => $this->type,
                        'classname' => $this->classname
                    ]
                ),
                'translatedText' => 'Aggiungi scadenza',
                'icon' => 'link'
            ]);

        $button->setAjaxTableButton();

        $this->getTable()->addButton(
            $button
        );
    }

    public function beforeRenderIndex()
    {
        $this->getTable()->setCaption(__('schedules::schedules.addTypeToClassnameModels', [
            'type' => $this->type->getTranslatedName(),
            'classname' => $this->placeholder->getSchedulableModelNameAttribute()
        ]));
    }

    public function getIndexFieldsArray()
    {
        $modelFields = $this->placeholder->getSchedulableModelTableFieldsArray();

        return config('schedules.models.type.fieldsGroupsFiles.applicableModelsElements')::getMergedAfter($modelFields);
    }

    public function index(Request $request, string $type, string $classname)
    {
        $this->type = Type::getByKey($type);
        $this->classname = $classname;
        $this->placeholder = $this->classname::make();
        $this->placeholder->setApplicatingScheduleType($this->type);

        return $this->_index($request);
    }

    public function getIndexElements()
    {
        return $this->placeholder->getSchedulableElements();
    }

    public function store(Request $request, string $type, string $classname)
    {
        $type = Type::getByKey($type);

        $placeholder = $classname::make();

        $request->validate([
            'ids' => 'required|exists:' . $placeholder->getTable() . ',' . $placeholder->getKeyName()
        ]);

        $elements = $classname::whereIn($placeholder->getKeyName(), $request->ids)->get();

        BulkScheduleApplicatorHelper::applicateScheduleToModels($type, $elements);

        return [
            'success' => true,
            'message' => __('schedules::schedules.typeAppliedSuccessfullyToCountElements', [
                'type' => $type->getName(),
                'count' => count($elements)
            ]),
            'ibaction' => 'reloadTable'
        ];
    }
}