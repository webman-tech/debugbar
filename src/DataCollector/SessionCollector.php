<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use Webman\Http\Request;

class SessionCollector extends DataCollector implements DataCollectorInterface, Renderable
{
    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    function collect()
    {
        $data = [];
        foreach ($this->request->session()->all() as $key => $value) {
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