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
	Route::get('applicate/{type}', [Schedules::getController('type', 'applicate'), 'index'])->name('types.applicate.index');


	Route::get('', [Schedules::getController('type', 'index'), 'index'])->name('types.index');
	Route::get('create', [Schedules::getController('type', 'create'), 'create'])->name('types.create');
	Route::post('', [Schedules::getController('type', 'store'), 'store'])->name('types.store');
	Route::get('{type}', [Schedules::getController('type', 'show'), 'show'])->name('types.show');
	Route::get('{type}/edit', [Schedules::getController('type', 'edit'), 'edit'])->name('types.edit');
	Route::put('{type}', [Schedules::getController('type', 'edit'), 'update'])->name('types.update');
	Route::delete('{type}/delete', [Schedules::getController('type', 'destroy'), 'destroy'])->name('types.destroy');	
});

	});