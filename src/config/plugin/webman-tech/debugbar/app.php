<?php

$enable = config('app.debug', false) && class_exists('WebmanTech\Debugbar\WebmanDebugBar');

return [
    'enable' => $enable,
    /**
     * @see \WebmanTech\Debugbar\WebmanDebugBar::$config
     */
    'debugbar' => [
        'enable' => $enable,
    ],
];
