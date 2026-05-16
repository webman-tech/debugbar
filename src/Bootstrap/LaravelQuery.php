<?php

namespace WebmanTech\Debugbar\Bootstrap;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use support\Db;
use Throwable;
use Webman\Bootstrap;
use WebmanTech\Debugbar\DataCollector\LaravelQueryCollector;
use WebmanTech\Debugbar\DebugBar;

class LaravelQuery implements Bootstrap
{
    private static bool $registered = false;

    /**
     * @inheritDoc
     */
    public static function start($worker)
    {
        if (!class_exists(Capsule::class) || !class_exists(Db::class)) {
            return;
        }

        $collectorName = (new LaravelQueryCollector())->getName();
        $debugBar = DebugBar::instance();
        $debugBar->boot();
        if (!$debugBar->hasCollector($collectorName)) {
            return;
        }
        /** @var LaravelQueryCollector $collector */
        $collector = $debugBar->getCollector($collectorName);

        self::listenForQueryEvents($collector);
    }

    private static function listenForQueryEvents(LaravelQueryCollector $collector): void
    {
        if (self::$registered) {
            return;
        }

        $container = Container::getInstance();
        if (!$container->bound('events')) {
            return;
        }

        $dispatcher = $container->make('events');
        if (!$dispatcher instanceof Dispatcher) {
            return;
        }

        try {
            $collector->addEventDispatcherListener($dispatcher);
            self::$registered = true;
        } catch (Throwable) {
            // Ignore debug collector listener registration errors.
        }
    }
}
