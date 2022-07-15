<?php

$enable = config('app.debug', false);

return [
    'enable' => $enable,
    /**
     * @see \Kriss\WebmanDebugBar\WebmanDebugBar::$config
     */
    'debugbar' => [
        'enable' => false,
        'collectors' => null,
        'http_driver' => true,
        'storage' => true,
        'open_handler_url' => '/_debugbar/open',
        'asset_base_url' => '/_debugbar/assets',
        'sample_url' => '/_debugbar/sample',
        'javascript_renderer_options' => [],
        'skip_request_path' => ['/_debugbar/open', '/_debugbar/assets/*', '*.css', '*.js'],
        'skip_request_callback' => null,
    ],
];
