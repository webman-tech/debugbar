<?php

namespace WebmanTech\Debugbar\DataCollector;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Str;
use WebmanTech\Debugbar\Laravel\DataFormatter\QueryFormatter;

class LaravelRedisCollector extends LaravelQueryCollector
{
    /**
     * @var array
     */
    protected $config = [
        'with_params' => true, // 将参数绑定上
        'backtrace' => true, // 显示 sql 来源
        'backtrace_exclude_paths' => [], // 排除 sql 来源
        'timeline' => true, // 将 sql 显示到 timeline 上
        'duration_background' => true, // 相对于执行所需的时间，在每个查询上显示阴影背景
        'show_copy' => true, // 显示复制
        'slow_threshold' => false, // 仅显示慢执行，设置毫秒时间来启用
    ];

    /**
     * @inheritDoc
     */
    function getName()
    {
        return 'laravelRedis';
    }

    protected function setConfig()
    {
        $this->mergeBacktraceExcludePaths([
            '/webman-debugbar/',
            '/vendor/webman-tech/debugbar',
            '/vendor/illuminate/support',
            '/vendor/illuminate/redis',
            '/vendor/illuminate/events',
            '/support/Redis.php',
        ]);

        $this->setDataFormatter(new QueryFormatter());

        if ($this->config['with_params']) {
            $this->setRenderSqlWithParams(true);
        }

        if ($this->config['backtrace']) {
            $middleware = [];
            $this->setFindSource(true, $middleware);
        }

        if ($this->config['backtrace_exclude_paths']) {
            $excludePaths = $this->config['backtrace_exclude_paths'];
            $this->mergeBacktraceExcludePaths($excludePaths);
        }

        $this->setDurationBackground($this->config['duration_background']);

        $this->setExplainSource(false, null);

        $this->setShowHints(false);

        if ($this->config['show_copy']) {
            $this->setShowCopyButton(true);
        }
    }

    public function addRedisListener(Connection $connection): void
    {
        $connection->listen(function (CommandExecuted $event) {
            $command = $event->command;
            $connection = $event->connection;
            $parameters = $event->parameters;
            $time = $event->time;

            if ($collector = $this->getRequestThisCollector()) {
                $collector->addExec($command, $parameters, $time, $connection);
            }
        });
    }

    /**
     * @param string $command
     * @param array $parameters
     * @param float $time
     * @param Connection $connection
     * @see addQuery
     */
    protected function addExec(string $command, array $parameters, float $time, Connection $connection)
    {
        $explainResults = [];
        $time = $time / 1000;
        $endTime = microtime(true);
        $startTime = $endTime - $time;

        if (!empty($parameters) && $this->renderSqlWithParams) {
            foreach ($parameters as &$item) {
                if (is_array($item)) {
                    $item = implode('\', \'', $item);
                }
            }
            unset($item);
            $command .= '(' . implode(', ', $parameters) . ')';
        }

        $source = [];

        if ($this->findSource) {
            try {
                $source = array_slice($this->findSource(), 0, 5);
            } catch (\Exception $e) {
            }
        }

        $this->queries[] = [
            'query' => $command,
            'type' => 'query',
            'bindings' => $this->getDataFormatter()->escapeBindings($parameters),
            'time' => $time,
            'source' => $source,
            'explain' => $explainResults,
            'connection' => $connection->getName(),
            'driver' => get_class($connection->client()),
            'hints' => null,
            'show_copy' => $this->showCopyButton,
        ];

        if ($this->timeCollector !== null) {
            $this->timeCollector->addMeasure(Str::limit($command, 100), $startTime, $endTime);
        }
    }
}