<?php

$enable = config('app.debug', false);

return [
    'enable' => $enable,
    /**
     * @see \Kriss\WebmanDebugBar\WebmanDebugBar::$config
     */
    'debugbar' => [
        'enable' => $enable,
        'collectors' => function() {
            return [];
        },
        'http_driver' => true,
        'storage' => true,
        'open_handler_url' => 'debugbar',
        'asset_base_url' => '/_debugbar',
    ],
];
