<?php

namespace Kriss\WebmanDebugBar\DataCollector;

use DebugBar\DataCollector\TimeDataCollector as DebugBarTimeDataCollector;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Kriss\WebmanDebugBar\Helper\ArrayHelper;
use Kriss\WebmanDebugBar\Laravel\DataCollector\QueryCollector;
use Kriss\WebmanDebugBar\Laravel\DataFormatter\QueryFormatter;
use support\Db;

class LaravelQueryCollector extends QueryCollector
{
    protected array $config = [
        'with_params' => true, // 将参数绑定上
        'backtrace' => true, // 显示 sql 来源
        'backtrace_exclude_paths' => [], // 排除 sql 来源
        'timeline' => true, // 将 sql 显示到 timeline 上
        'duration_background' => false, // 相对于执行所需的时间，在每个查询上显示阴影背景
        'explain' => [
            'enabled' => false,
            'types' => ['SELECT'], // 废弃的设置，目前只支持 SELECT
        ],
        'hints' => false, // 显示常见错误提示
        'show_copy' => true, // 显示复制
        'slow_threshold' => false, // 仅显示慢sql，设置毫秒时间来启用
    ];

    public function __construct(array $config, DebugBarTimeDataCollector $timeCollector = null)
    {
        $this->config = ArrayHelper::merge($this->config, $config);

        if (!$this->config['timeline']) {
            $timeCollector = null;
        }

        parent::__construct($timeCollector);

        $this->mergeBacktraceExcludePaths([
            '/kriss/webman-debugbar',
            '/vendor/kriss/webman-debugbar',
            '/vendor/illuminate/support',
            '/vendor/illuminate/database',
            '/vendor/illuminate/events',
        ]);

        $this->setConfig();
        $this->addListener(Db::connection());
    }

    protected function setConfig()
    {
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

        if ($this->config['explain']['enabled']) {
            $types = $this->config['explain']['types'];
            $this->setExplainSource(true, $types);
        }

        if ($this->config['hints']) {
            $this->setShowHints(true);
        }

        if ($this->config['show_copy']) {
            $this->setShowCopyButton(true);
        }
    }

    protected function addListener(Connection $db)
    {
        $db->listen(function (QueryExecuted $event) {
            $bindings = $event->bindings;
            $time = $event->time;
            $connection = $event->connection;
            $query = $event->sql;

            $threshold = $this->config['slow_threshold'];
            if (!$threshold || $time > $threshold) {
                $this->addQuery($query, $bindings, $time, $connection);
            }
        });

        $db->getEventDispatcher()->listen(
            \Illuminate\Database\Events\TransactionBeginning::class,
            function ($transaction) {
                $this->collectTransactionEvent('Begin Transaction', $transaction->connection);
            }
        );

        $db->getEventDispatcher()->listen(
            \Illuminate\Database\Events\TransactionCommitted::class,
            function ($transaction) {
                $this->collectTransactionEvent('Commit Transaction', $transaction->connection);
            }
        );

        $db->getEventDispatcher()->listen(
            \Illuminate\Database\Events\TransactionRolledBack::class,
            function ($transaction) {
                $this->collectTransactionEvent('Rollback Transaction', $transaction->connection);
            }
        );

        $db->getEventDispatcher()->listen(
            'connection.*.beganTransaction',
            function ($event, $params) {
                $this->collectTransactionEvent('Begin Transaction', $params[0]);
            }
        );

        $db->getEventDispatcher()->listen(
            'connection.*.committed',
            function ($event, $params) {
                $this->collectTransactionEvent('Commit Transaction', $params[0]);
            }
        );

        $db->getEventDispatcher()->listen(
            'connection.*.rollingBack',
            function ($event, $params) {
                $this->collectTransactionEvent('Rollback Transaction', $params[0]);
            }
        );
    }
}