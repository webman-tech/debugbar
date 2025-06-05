<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\TimeDataCollector as OriginTimeDataCollector;
use WebmanTech\Debugbar\Middleware\DebugBarMiddleware;

class TimeDataCollector extends OriginTimeDataCollector
{
    public function __construct($requestStartTime = null)
    {
        parent::__construct($requestStartTime);

        DebugBarMiddleware::bindEventWhenRequestStart(function () {
            $this->requestStartTime = microtime(true);
            $this->measures = [];
        });
    }
}
