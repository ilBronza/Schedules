<?php

namespace IlBronza\Schedules\Http\Controllers\Schedules;

use IlBronza\Buttons\Button;
use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Traits\CRUDIndexTrait;
use IlBronza\CRUD\Traits\CRUDPlainIndexTrait;
use IlBronza\CRUD\Traits\CRUDRelationshipTrait;
use IlBronza\CRUD\Traits\CRUDShowTrait;
use IlBronza\Schedules\Http\Controllers\CrudSchedulesCrudController;
use IlBronza\Schedules\Services\ScheduleManager;
use Illuminate\Http\Request;

class ScheduleCrudController extends CrudSchedulesCrudController
{
	use CRUDIndexTrait;
	use CRUDPlainIndexTrait;
    use CRUDShowTrait;

    use CRUDRelationshipTrait;

    public $configModelClassName = 'schedule';
    public $avoidCreateButton = true;
    public $canEditModelInstance = false;

    public function getEditModelIsntanceUrl()
    {
        return false;
    }

	public $allowedMethods = [
		'index',
        'show',
        'manage',
        'manageMany',
	];

    public function addIndexButtons(): void
    {
        $button = Button::create([
            'href' => IbRouter::route(
                app('schedules'),
                'schedules.manageMany'
            ),
            'text' => 'schedules::schedules.manageSelected',
            'icon' => 'check',
        ]);

        $button->setAjaxTableButton();
        $button->setReturnConfirm(
            __('schedules::schedules.confirmManageSelected')
        );

        $this->getTable()->addButton($button);
    }

	public function getIndexElements()
	{
		$query = $this->getModelClass()::with([
			'type',
			'schedulable',
			'typeNotifications'
		]);

		return $query->get();
	}

	public function show(string $schedule)
    {
        $schedule = $this->findModel($schedule);

        return $this->_show($schedule);
    }

    public function manage(
        string $schedule,
        ScheduleManager $manager
    ): array {
        $managedSchedule = $manager->manage(
            $this->findModel($schedule)
        );

        return $this->managedResponse(
            1,
            $managedSchedule->getKey()
        );
    }

    public function manageMany(
        Request $request,
        ScheduleManager $manager
    ): array {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'string',
                'distinct',
                'exists:' . config('schedules.models.schedule.table') . ',id',
            ],
        ]);

        $managedSchedules = $manager->manageMany($validated['ids']);

        return $this->managedResponse($managedSchedules->count());
    }

    protected function managedResponse(
        int $count,
        ?string $scheduleId = null
    ): array {
        return [
            'success' => true,
            'message' => trans_choice(
                'schedules::schedules.managedSuccessfully',
                $count,
                ['count' => $count]
            ),
            'schedule_id' => $scheduleId,
            'ibaction' => 'reloadTable',
        ];
    }
}
