<?php

namespace WebmanTech\Debugbar;

use Closure;
use Throwable;

/**
 * @method static void addThrowable(Throwable $e)
 * @method static void addMessage($message, string $type = 'info')
 * @method static void startMeasure(string $name, string $label = null)
 * @method static void stopMeasure(string $name)
 * @method static void addMeasure(string $label, float $start, float $end)
 * @method static mixed measure(string $label, Closure $closure)
 */
class DebugBar
{
    public const REQUEST_KEY = '_debugbar_request_instance';

    /**
     * @var null|WebmanDebugBar
     */
    protected static $_instance = null;

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
            $config = config('plugin.kriss.webman-debugbar.app.debugbar', []);
            $request->{static::REQUEST_KEY} = static::createDebugBar($config);
        }
        return $request->{static::REQUEST_KEY};
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