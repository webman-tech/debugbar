<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\MessagesCollector as OriginPhpInfoCollector;
use WebmanTech\Debugbar\Middleware\DebugBarMiddleware;

class MessagesCollector extends OriginPhpInfoCollector
{
    public function __construct($name = 'messages')
    {
        parent::__construct($name);

        DebugBarMiddleware::bindEventWhenRequestStart(function () {
            $this->clear();
        });
    }
}
