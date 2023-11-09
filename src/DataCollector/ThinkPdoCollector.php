<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\TimeDataCollector;
use think\db\PDOConnection;
use think\facade\Db;

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

        foreach ($connectionNames as $name) {
            $connection = Db::connect($name);
            if ($connection instanceof PDOConnection) {
                $pdo = $connection->connect();
                $this->addConnection(new TraceablePDO($pdo), $name);
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