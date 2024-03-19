<?php

namespace WebmanTech\Debugbar\DataCollector\PDO;

class TraceablePDO extends \DebugBar\DataCollector\PDO\TraceablePDO
{
    /**
     * 清空历史
     * @return void
     */
    public function cleanExecutedStatements()
    {
        $this->executedStatements = [];
    }
}