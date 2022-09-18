<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;

class RouteCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    /**
     * @inheritDoc
     */
    function collect()
    {
        $request = request();
        $data = [];
        if ($route = $request->route) {
            $data = [
                'uri' => $request->method() . ' ' . $request->uri(),
                'controller' => $request->controller,
                'action' => $request->action,
                'name' => $route->getName(),
                'path' => $route->getPath(),
                'methods' => $route->getMethods(),
                'middleware' => $route->getMiddleware(),
            ];
        }
        foreach ($data as &$value) {
            if (!is_string($value)) {
                $value = $this->getDataFormatter()->formatVar($value);
            }
        }
        unset($value);
        return $data;
    }

    /**
     * @inheritDoc
     */
    function getName()
    {
        return 'route';
    }

    /**
     * @inheritDoc
     */
    function getWidgets()
    {
        return [
            "route" => [
                "icon" => "share",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "route",
                "default" => "{}"
            ],
            "currentRoute" => [
                "icon" => "share",
                "tooltip" => "Route",
                "map" => "route.uri",
                "default" => ""
            ],
        ];
    }
}