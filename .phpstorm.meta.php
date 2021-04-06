<?php
namespace PHPSTORM_META {
    override(\Illuminate\Contracts\Container\Container::make(0), map([
        '' => '@',
        'log' => \Psr\Log\LoggerInterface::class,
        'view' => \Illuminate\View\Factory::class,
        'events' => \Illuminate\Contracts\Events\Dispatcher::class,
        'db' => \Illuminate\Database\DatabaseManager::class,
        'view.engine.resolver' => \Illuminate\View\Engines\EngineResolver::class,
    ]));
    override(\Illuminate\Foundation\Application::make(0), map([
        '' => '@',
        'view' => \Illuminate\View\Factory::class,
    ]));
    override(\Illuminate\Contracts\Foundation\Application::make(0), map([
        '' => '@',
        'log' => \Psr\Log\LoggerInterface::class,
        'view' => \Illuminate\View\Factory::class,
        'events' => \Illuminate\Contracts\Events\Dispatcher::class,
        'db' => \Illuminate\Database\DatabaseManager::class,
        'view.engine.resolver' => \Illuminate\View\Engines\EngineResolver::class,
    ]));
    override(\Laravel\Lumen\Application::make(0), map([
        '' => '@',
        'log' => \Psr\Log\LoggerInterface::class,
        'view' => \Illuminate\View\Factory::class,
        'events' => \Illuminate\Contracts\Events\Dispatcher::class,
        'db' => \Illuminate\Database\DatabaseManager::class,
        'view.engine.resolver' => \Illuminate\View\Engines\EngineResolver::class,
    ]));
}
