<?php

use IlBronza\Schedules\Schedules;

Route::group([
	'middleware' => ['web', 'auth'],
	'prefix' => 'schedules-management',
	'as' => config('schedules.routePrefix')
	],
	function()
	{

Route::group(['prefix' => 'types'], function()
{
	Route::group(['prefix' => 'applicate/{type}'], function()
	{
		Route::get('/models/{classname}', [Schedules::getController('type', 'applicateClassname'), 'index'])->name('types.applicate.classname.index');
		Route::post('/models/{classname}', [Schedules::getController('type', 'applicateClassname'), 'store'])->name('types.applicate.classname.store');
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
	Route::put('{type}', [Schedules::getController('type', 'edit'), 'update'])->name('types.update');
	Route::delete('{type}/delete', [Schedules::getController('type', 'destroy'), 'destroy'])->name('types.destroy');
});

Route::group(['prefix' => 'type-notifications'], function()
{
	Route::get('', [Schedules::getController('typeNotification', 'index'), 'index'])->name('typeNotifications.index');
	Route::get('create', [Schedules::getController('typeNotification', 'create'), 'create'])->name('typeNotifications.create');
	Route::get('{typeNotification}', [Schedules::getController('typeNotification', 'show'), 'show'])->name('typeNotifications.show');
	Route::get('{typeNotification}/edit', [Schedules::getController('typeNotification', 'edit'), 'edit'])->name('typeNotifications.edit');
	Route::put('{typeNotification}', [Schedules::getController('typeNotification', 'edit'), 'update'])->name('typeNotifications.update');
	Route::delete('{typeNotification}/delete', [Schedules::getController('typeNotification', 'destroy'), 'destroy'])->name('typeNotifications.destroy');
});


Route::group(['prefix' => 'schedules'], function()
{
	Route::get('', [Schedules::getController('schedule', 'index'), 'index'])->name('schedules.index');
	Route::get('create', [Schedules::getController('schedule', 'create'), 'create'])->name('schedules.create');
	Route::post('', [Schedules::getController('schedule', 'store'), 'store'])->name('schedules.store');
	Route::get('{schedule}', [Schedules::getController('schedule', 'show'), 'show'])->name('schedules.show');
	Route::get('{schedule}/edit', [Schedules::getController('schedule', 'edit'), 'edit'])->name('schedules.edit');
	Route::put('{schedule}', [Schedules::getController('schedule', 'edit'), 'update'])->name('schedules.update');
	Route::delete('{schedule}/delete', [Schedules::getController('schedule', 'destroy'), 'destroy'])->name('schedules.destroy');
});



	});