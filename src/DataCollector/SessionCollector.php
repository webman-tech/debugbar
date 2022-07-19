<?php

namespace Kriss\WebmanDebugBar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;

class SessionCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    /**
     * @inheritDoc
     */
    function collect()
    {
        $data = [];
        foreach (request()->session()->all() as $key => $value) {
            $data[$key] = is_string($value) ? $value : $this->getDataFormatter()->formatVar($value);
        }
        return $data;
    }

    /**
     * @inheritDoc
     */
    function getName()
    {
        return 'session';
    }

    /**
     * @inheritDoc
     */
    function getWidgets()
    {
        return [
            "session" => [
                "icon" => "archive",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "session",
                "default" => "{}"
            ]
        ];
    }
}