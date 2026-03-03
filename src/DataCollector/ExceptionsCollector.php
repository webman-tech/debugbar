<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\ExceptionsCollector as OriginPhpInfoCollector;
use WebmanTech\Debugbar\Middleware\DebugBarMiddleware;

class ExceptionsCollector extends OriginPhpInfoCollector
{
    public function __construct()
    {
        DebugBarMiddleware::bindEventWhenRequestEnd($this, function (): void {
            $this->exceptions = [];
        });
    }
}
