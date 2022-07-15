<?php

$enable = config('app.debug', false);

return [
    'enable' => $enable,
    /**
     * @see \Kriss\WebmanDebugBar\WebmanDebugBar::$config
     */
    'debugbar' => [
        'enable' => $enable,
        'collectors' => null, // 其他 collectors，使用 callable 返回 DataCollectorInterface 数组
        'http_driver' => true, // 定义 http_driver
        'storage' => true, // 定义 storage
        'open_handler_url' => '/_debugbar/open', // storage 启用时打开历史的路由
        'asset_base_url' => '/_debugbar/assets', // 静态资源的路由
        'sample_url' => '/_debugbar/sample', // 示例页面，可用于查看 debugbar 信息，设为 null 关闭
        'javascript_renderer_options' => [], // 其他 javascriptRenderer 参数
        'skip_request_path' => [ // 需要忽略的请求路由
            '/_debugbar/open',
            '/_debugbar/assets/*',
            '*.css',
            '*.js',
        ],
        'skip_request_callback' => null, // 需要忽略的请求 callback 处理
        'options' => [
            'db' => [
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
            ]
        ],
    ],
];
