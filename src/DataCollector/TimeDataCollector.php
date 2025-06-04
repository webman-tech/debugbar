<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\TimeDataCollector as OriginTimeDataCollector;

class TimeDataCollector extends OriginTimeDataCollector
{
    public const REQUEST_KEY = '_debugbar_request_time';

    public function __construct($requestStartTime = null)
    {
        if ($requestStartTime === null) {
            if ($request = request()) {
                if (!$request->{static::REQUEST_KEY}) {
                    $request->{static::REQUEST_KEY} = microtime(true);
                }
                $requestStartTime = $request->{static::REQUEST_KEY};
            }
        }

        parent::__construct($requestStartTime);
    }
}