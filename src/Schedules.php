<?php

namespace IlBronza\Schedules;

use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\CRUD\Providers\RouterProvider\RoutedObjectInterface;

class Schedules implements RoutedObjectInterface
{
    public function manageMenuButtons()
    {
        if(! $menu = app('menu'))
            return;

        $settingsButton = $menu->provideButton([
                'text' => 'generals.settings',
                'name' => 'settings',
                'icon' => 'gear',
                'roles' => ['administrator']
            ]);

        $schedulesManagerButton = $menu->createButton([
            'name' => 'schedulesManager',
            'icon' => 'user-gear',
            'text' => 'schedules::schedules.list',
        ]);

        $settingsButton->addChild($schedulesManagerButton);

        $schedulesManagerButton->addChild(
            $menu->createButton([
                'name' => 'types.list',
                'icon' => 'truck-moving',
                'text' => 'schedules::types.list',
                'href' => IbRouter::route($this, 'types.index')
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

    public function getRoutePrefix() : ? string
    {
        return config('schedules.routePrefix');
    }

    static function getController(string $target, string $controllerPrefix) : string
    {
        try
        {
            return config("schedules.models.{$target}.controllers.{$controllerPrefix}");
        }
        catch(\Throwable $e)
        {
            dd([$e->getMessage(), 'dichiara ' . "schedules.models.{$target}.controllers.{$controllerPrefix}"]);
        }
    }

}