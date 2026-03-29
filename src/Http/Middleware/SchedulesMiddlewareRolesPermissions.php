<?php

namespace IlBronza\Schedules\Http\Middleware;

use IlBronza\CRUD\Middleware\CRUDBasePackageMiddlewareRolesPermissions;

/**
 * Resolves allowed roles for Schedules routes from config (schedules.defaultRoles / schedules.routeRoles).
 */
class SchedulesMiddlewareRolesPermissions extends CRUDBasePackageMiddlewareRolesPermissions
{
    protected string $configPackageName = 'schedules';
}
