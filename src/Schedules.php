<?php

namespace IlBronza\Schedules;

use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Providers\RouterProvider\RoutedObjectInterface;
use IlBronza\CRUD\Traits\IlBronzaPackages\IlBronzaPackagesTrait;

class Schedules implements RoutedObjectInterface
{
	use IlBronzaPackagesTrait;

	static $packageConfigPrefix = 'schedules';

    public function manageMenuButtons()
    {
        if(! $menu = app('menu'))
            return;

        $settingsButton = $menu->provideButton([
                'text' => 'menu::menu.settings',
                'name' => 'settings',
                'icon' => 'gear',
                'roles' => ['administrator']
            ]);

        $schedulesManagerButton = $menu->createButton([
            'name' => 'schedulesManager',
            'icon' => 'calendar-days',
            'text' => 'schedules::schedules.list',
        ]);

        $settingsButton->addChild($schedulesManagerButton);

		$schedulesManagerButton->addChild(
			$menu->createButton([
				'name' => 'types.list',
				'icon' => 'gear',
				'text' => 'schedules::types.list',
				'href' => IbRouter::route($this, 'types.index')
			])
		);

		$schedulesManagerButton->addChild(
			$menu->createButton([
				'name' => 'schedules.list',
				'icon' => 'gear',
				'text' => 'schedules::schedules.list',
				'href' => IbRouter::route($this, 'schedules.index')
			])
		);

		// $schedulesManagerButton->addChild(
        //     $menu->createButton([
        //         'name' => 'schedules.types.list',
        //         'icon' => 'gear',
        //         'text' => 'schedules::schedules.types',
        //         'href' => IbRouter::route($this, 'types.index')
        //     ])
        // );
        // $schedulesManagerButton->addChild(
        //         $menu->createButton([
        //         'name' => 'schedules.kmreadings.list',
        //         'icon' => 'bookmark',
        //         'text' => 'schedules::schedules.kmreadings',
        //         'href' => IbRouter::route($this, 'kmreadings.index')
        //     ])
        // );
    }

}