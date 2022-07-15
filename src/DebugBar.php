<?php

namespace Kriss\WebmanDebugBar;

class DebugBar
{
    protected static ?WebmanDebugBar $_instance = null;

    public static function instance(): WebmanDebugBar
    {
        if (!static::$_instance) {
            $config = config('plugin.kriss.webman-debugbar.app.debugbar', []);
            static::$_instance = static::createDebugBar($config);
        }
        return static::$_instance;
    }

    protected static function createDebugBar(array $config): WebmanDebugBar
    {
        return new WebmanDebugBar($config);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return static::instance()->{$name}(... $arguments);
    }
}