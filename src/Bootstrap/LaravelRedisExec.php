<?php

namespace WebmanTech\Debugbar\Bootstrap;

use Illuminate\Redis\RedisManager;
use WebmanTech\Debugbar\DataCollector\LaravelRedisCollector;
use WebmanTech\Debugbar\DebugBar;
use support\Redis;
use Webman\Bootstrap;

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
        $connections = array_keys(config('redis'));
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