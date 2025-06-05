<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Workerman\Worker;

class WebmanCollector extends DataCollector implements Renderable
{
    /**
     * @inheritDoc
     */
    public function collect()
    {
        return [
            "version" => 'Worker: ' . Worker::VERSION,
            "environment" => config('app.debug', false) ? 'debug' : 'prod',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'webman';
    }

    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        return [
            "version" => [
                "icon" => "github",
                "tooltip" => "Webman Version",
                "map" => "webman.version",
                "default" => ""
            ],
            "environment" => [
                "icon" => "desktop",
                "tooltip" => "Environment",
                "map" => "webman.environment",
                "default" => ""
            ],
        ];
    }
}
