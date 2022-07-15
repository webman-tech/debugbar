<?php

namespace Kriss\WebmanDebugBar;

class DebugBar
{
    const REQUEST_KEY = '_debugbar_request_instance';

    protected static ?WebmanDebugBar $_instance = null;
    protected static ?WebmanDebugBar $_instanceRequest = null;

    public static function instance(): WebmanDebugBar
    {
        $request = request();
        // 非 request 请求使用一个实例
        if (!$request) {
            if (!static::$_instance) {
                $config = config('plugin.kriss.webman-debugbar.app.debugbar', []);
                static::$_instance = static::createDebugBar($config);
            }
            return static::$_instance;
        }

        // 每个 request 请求单独创建一个实例
        if (!$request->{static::REQUEST_KEY}) {
            $request->{static::REQUEST_KEY} = true;
            static::$_instanceRequest = null;
        }
        if (!static::$_instanceRequest) {
            $config = config('plugin.kriss.webman-debugbar.app.debugbar', []);
            static::$_instanceRequest = static::createDebugBar($config);
        }
        return static::$_instanceRequest;
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