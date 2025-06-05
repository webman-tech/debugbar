<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\MemoryCollector as OriginMemoryCollector;
use WebmanTech\Debugbar\Middleware\DebugBarMiddleware;

class MemoryCollector extends OriginMemoryCollector
{
    public function __construct()
    {
        DebugBarMiddleware::bindEventWhenRequestStart(function (): void {
            $this->resetMemoryBaseline();
        });
    }

    /**
     * @inheritdoc
     */
    public function getWidgets()
    {
        return [
            "memory" => [
                "icon" => "cogs",
                "tooltip" => "内存使用: 仅表示本次启动后的最大内存",
                "map" => "memory.peak_usage_str",
                "default" => "'0B'"
            ]
        ];
    }
}
