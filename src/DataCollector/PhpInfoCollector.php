<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\PhpInfoCollector as OriginPhpInfoCollector;

class PhpInfoCollector extends OriginPhpInfoCollector
{
    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        $widgets = parent::getWidgets();
        $widgets['php_version']['tooltip'] = 'PHP Version';
        return $widgets;
    }
}