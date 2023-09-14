<?php

namespace IlBronza\Schedules\Http\Controllers\ScheduledNotifications;

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

class ScheduledNotificationCrudController extends CrudSchedulesCrudController
{
    use CRUDIndexTrait;
    use CRUDPlainIndexTrait;
    use CRUDCreateStoreTrait;
    use CRUDEditUpdateTrait;
    use CRUDShowTrait;
    use CRUDDeleteTrait;

    use CRUDRelationshipTrait;

    public $configModelClassName = 'scheduledNotification';
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

    public function show(string $scheduledNotification)
    {
        $scheduledNotification = $this->findModel($scheduledNotification);

        return $this->_show($scheduledNotification);
    }

    public function edit(string $scheduledNotification)
    {
        $scheduledNotification = $this->findModel($scheduledNotification);

        return $this->_edit($scheduledNotification);
    }

    public function update(Request $request, string $scheduledNotification)
    {
        $scheduledNotification = $this->findModel($scheduledNotification);

        return $this->_update($request, $scheduledNotification);
    }

    public function destroy($scheduledNotification)
    {
        $scheduledNotification = $this->findModel($scheduledNotification);

        return $this->_destroy($scheduledNotification);
    }
}