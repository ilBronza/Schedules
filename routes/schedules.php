<?php

use IlBronza\Schedules\Schedules;

Route::group([
	'middleware' => ['web', 'auth', 'schedules.roles'],
	'prefix' => 'schedules-management',
	'as' => config('schedules.routePrefix'),
	'routeTranslationPrefix' => Schedules::getRouteTranslationPrefix(),
	],
	function()
	{

Route::group(['prefix' => 'types'], function()
{
	Route::group(['prefix' => 'applicate/{type}'], function()
	{
		Route::get('/models/{alias}', [Schedules::getController('type', 'applicateClassname'), 'index'])->name('types.applicate.classname.index');
		Route::post('/models/{alias}', [Schedules::getController('type', 'applicateClassname'), 'store'])->name('types.applicate.classname.store');
		Route::get('', [Schedules::getController('type', 'applicate'), 'index'])->name('types.applicate.index');
	});

	Route::group(['prefix' => '{type}/type-notification'], function()
	{
		Route::get('create', [Schedules::getController('typeNotification', 'create'), 'createFromType']
		)->name('types.typeNotifications.create');

		Route::post('', [Schedules::getController('typeNotification', 'store'), 'store'])->name('types.typeNotifications.store');
	});

	Route::get('', [Schedules::getController('type', 'index'), 'index'])->name('types.index');
	Route::get('create', [Schedules::getController('type', 'create'), 'create'])->name('types.create');
	Route::post('', [Schedules::getController('type', 'store'), 'store'])->name('types.store');
	Route::get('{type}', [Schedules::getController('type', 'show'), 'show'])->name('types.show');
	Route::get('{type}/edit', [Schedules::getController('type', 'edit'), 'edit'])->name('types.edit');
	Route::put('{type}', [Schedules::getController('type', 'update'), 'update'])->name('types.update');
	Route::delete('{type}/delete', [Schedules::getController('type', 'destroy'), 'destroy'])->name('types.destroy');
});

Route::group(['prefix' => 'type-notifications'], function()
{
	Route::get('', [Schedules::getController('typeNotification', 'index'), 'index'])->name('typeNotifications.index');
	Route::get('{typeNotification}', [Schedules::getController('typeNotification', 'show'), 'show'])->name('typeNotifications.show');
	Route::get('{typeNotification}/edit', [Schedules::getController('typeNotification', 'edit'), 'edit'])->name('typeNotifications.edit');
		Route::put('{typeNotification}', [Schedules::getController('typeNotification', 'update'), 'update'])->name('typeNotifications.update');
	Route::delete('{typeNotification}/delete', [Schedules::getController('typeNotification', 'destroy'), 'destroy'])->name('typeNotifications.destroy');
});


Route::group(['prefix' => 'schedules'], function()
{
	Route::get('', [Schedules::getController('schedule', 'index'), 'index'])->name('schedules.index');
	Route::post('manage', [Schedules::getController('schedule', 'manageMany'), 'manageMany'])->name('schedules.manageMany');
	Route::post('{schedule}/manage', [Schedules::getController('schedule', 'manage'), 'manage'])->name('schedules.manage');
	Route::get('{schedule}', [Schedules::getController('schedule', 'show'), 'show'])->name('schedules.show');
});



	});