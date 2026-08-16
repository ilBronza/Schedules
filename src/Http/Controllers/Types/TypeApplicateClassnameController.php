<?php

namespace IlBronza\Schedules\Http\Controllers\Types;

use IlBronza\Buttons\Button;
use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\Schedules\Helpers\Applicators\BulkScheduleApplicatorHelper;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use IlBronza\Schedules\Models\Type;
use Illuminate\Http\Request;
use RuntimeException;

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
                        'alias' => $this->alias
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

    public function index(Request $request, string $type, string $alias)
    {
        $this->type = Type::getByKey($type);
        $this->alias = $alias;
        $this->classname = $this->resolveClassnameFromAlias($alias, $this->type);
        $this->placeholder = $this->classname::make();
        $this->placeholder->setApplicatingScheduleType($this->type);

        return $this->_index($request);
    }

    public function getIndexElements()
    {
        return $this->placeholder->getSchedulableElements();
    }

    public function store(Request $request, string $type, string $alias)
    {
        $type = Type::getByKey($type);
        $classname = $this->resolveClassnameFromAlias($alias, $type);

        $placeholder = $classname::make();
        $keyName = $placeholder->getKeyName();
        $table = $placeholder->getTable();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'distinct',
                'exists:' . $table . ',' . $keyName,
            ],
        ]);

        $ids = $validated['ids'];
        $elements = $classname::whereIn($keyName, $ids)->get();

        if ($elements->count() !== count($ids)) {
            throw new RuntimeException(
                'Loaded schedule applicable models count [' . $elements->count()
                . '] does not match requested ids count [' . count($ids) . ']'
            );
        }

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

    protected function resolveClassnameFromAlias(string $alias, Type $type): string
    {
        $classname = array_search(
            $alias,
            config('schedules.applicableTo', []),
            true
        );

        if ($classname === false) {
            throw new RuntimeException(
                "Unknown schedule applicable alias [{$alias}]"
            );
        }

        if (! in_array($classname, $type->getAvailableModels(), true)) {
            throw new RuntimeException(
                "Schedule applicable model [{$classname}] is not allowed for type [{$type->getKey()}]"
            );
        }

        return $classname;
    }
}
