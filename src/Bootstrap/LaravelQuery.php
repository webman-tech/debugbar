<?php

namespace WebmanTech\Debugbar\Bootstrap;

use Illuminate\Database\Capsule\Manager as Capsule;
use WebmanTech\Debugbar\DataCollector\LaravelQueryCollector;
use WebmanTech\Debugbar\DebugBar;
use support\Db;
use Webman\Bootstrap;

class LaravelQuery implements Bootstrap
{
    /**
     * @inheritDoc
     */
    public static function start($worker)
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $connections = array_keys(config('database.connections'));
        if ($default = config('database.default')) {
            $connections[] = $default;
        }
        if (!$connections) {
            return;
        }
        $connections = array_unique($connections);

        $collectorName = (new LaravelQueryCollector())->getName();
        $debugBar = DebugBar::instance();
        $debugBar->boot();
        if (!$debugBar->hasCollector($collectorName)) {
            return;
        }
        /** @var LaravelQueryCollector $collector */
        $collector = $debugBar->getCollector($collectorName);

        foreach ($connections as $connection) {
            $collector->addListener(Db::connection($connection));
        }
    }
}