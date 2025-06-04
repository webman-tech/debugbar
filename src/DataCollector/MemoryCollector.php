<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\MemoryCollector as OriginMemoryCollector;

class MemoryCollector extends OriginMemoryCollector
{
    /**
     * @inheritdoc
     */
    public function getWidgets()
    {
        return array(
            "memory" => array(
                "icon" => "cogs",
                "tooltip" => "内存使用: 仅表示本次启动后的最大内存",
                "map" => "memory.peak_usage_str",
                "default" => "'0B'"
            )
        );
    }
}