<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\App;

use Phalcon\Di\DiInterface;
use Phalcon\Events\Manager;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\ModuleDefinitionInterface;

final class Module implements ModuleDefinitionInterface
{
    public function registerAutoloaders(?DiInterface $container = null): void
    {
    }

    public function registerServices(DiInterface $container): void
    {
        $container->set('dispatcher', static function (): Dispatcher {
            $dispatcher = new Dispatcher();
            $dispatcher->setEventsManager(new Manager());
            $dispatcher->setDefaultNamespace(
                'Modules\ModuleRemoteSupport\App\Controllers\\',
            );

            return $dispatcher;
        });
    }
}
