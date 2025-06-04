<?php

namespace WebmanTech\Debugbar\DataCollector\PDO;

use DebugBar\DataCollector\PDO\TraceablePDO as DebugbarTraceablePDO;
use DebugBar\DataCollector\TimeDataCollector;

class PDOCollector extends \DebugBar\DataCollector\PDO\PDOCollector
{
    /**
     * @inheritDoc
     */
    protected function collectPDO(DebugbarTraceablePDO $pdo, TimeDataCollector $timeCollector = null, $connectionName = null)
    {
        $data = parent::collectPDO($pdo, $timeCollector, $connectionName);

        if ($pdo instanceof TraceablePDO) {
            // 清除历史记录，否则在 webman 常驻内存的情况下会有历史累计
            $pdo->cleanExecutedStatements();
        }

        return $data;
    }
}