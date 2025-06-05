<?php

namespace WebmanTech\Debugbar\Bootstrap;

use Illuminate\Redis\RedisManager;
use support\Redis;
use Webman\Bootstrap;
use WebmanTech\Debugbar\DataCollector\LaravelRedisCollector;
use WebmanTech\Debugbar\DebugBar;

class LaravelRedisExec implements Bootstrap
{
    /**
     * @inheritDoc
     */
    public static function start($worker)
    {
        if (!class_exists(RedisManager::class)) {
            return;
        }
        /** @var string[] $connections */
        $connections = array_keys((array)config('redis'));
        if (!$connections) {
            return;
        }

        $collectorName = (new LaravelRedisCollector())->getName();
        $debugBar = DebugBar::instance();
        $debugBar->boot();
        if (!$debugBar->hasCollector($collectorName)) {
            return;
        }
        /** @var LaravelRedisCollector $collector */
        $collector = $debugBar->getCollector($collectorName);

        foreach ($connections as $connection) {
            try {
                $connection = Redis::connection($connection);
                $collector->addRedisListener($connection);
            } catch (\Throwable $e) {
                // 忽略错误的 redis connection
            }
        }
    }
}
