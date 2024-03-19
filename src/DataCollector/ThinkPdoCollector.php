<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\TimeDataCollector;
use think\db\PDOConnection;
use think\facade\Db;
use WebmanTech\Debugbar\DataCollector\PDO\PDOCollector;
use WebmanTech\Debugbar\DataCollector\PDO\TraceablePDO;

class ThinkPdoCollector extends PDOCollector
{
    public function __construct(array $thinkOrmConfig = [], TimeDataCollector $timeCollector = null)
    {
        $config = array_merge([
            'default' => '',
            'connections' => [],
        ], $thinkOrmConfig);

        $connectionNames = array_keys($config['connections']);
        $connectionNames[] = $config['default'] ?? '';
        $connectionNames = array_unique($connectionNames);

        foreach ($connectionNames as $connectionName) {
            $connection = Db::connect($connectionName);
            if ($connection instanceof PDOConnection) {
                $dbConfig = $config['connections'][$connectionName] ?? [];
                if (!empty($dbConfig['deploy'])) {
                    /**
                     * 分布式支持
                     * @link https://doc.thinkphp.cn/v8_0/distributed.html
                     * @see PDOConnection::multiConnect()
                     */
                    $multiConfig = [];
                    foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
                        $dbConfig[$name] = $dbConfig[$name] ?? '';
                        $multiConfig[$name] = is_string($dbConfig[$name]) ? explode(',', $dbConfig[$name]) : $dbConfig[$name];
                    }
                    $multiDBConfig = [];
                    foreach ($multiConfig['hostname'] as $index => $hostname) {
                        $tempConfig = [];
                        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
                            $tempConfig[$name] = $multiConfig[$name][$index] ?? $multiConfig[$name][0];
                        }
                        $multiDBConfig[] = [
                            'hostname' => $hostname,
                            'config' => $tempConfig,
                        ];
                    }
                    foreach ($multiDBConfig as $index => $item) {
                        $pdo = $connection->connect($item['config'], $index);
                        $this->addConnection(new TraceablePDO($pdo), $connectionName . ':' . $item['hostname']);
                    }
                } else {
                    // 单机
                    $pdo = $connection->connect();
                    $this->addConnection(new TraceablePDO($pdo), $connectionName);
                }
            }
        }

        parent::__construct(null, $timeCollector);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'ThinkDB';
    }

    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        $name = $this->getName();
        return array(
            $name => array(
                "icon" => "database",
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => $name,
                "default" => "[]"
            ),
            "{$name}:badge" => array(
                "map" => "{$name}.nb_statements",
                "default" => 0
            )
        );
    }
}