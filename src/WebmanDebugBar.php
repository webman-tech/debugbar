<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use DebugBar\Storage\FileStorage;
use Kriss\WebmanDebugBar\DataCollector\LaravelQueryCollector;
use Kriss\WebmanDebugBar\DataCollector\MemoryCollector;
use Kriss\WebmanDebugBar\DataCollector\RequestDataCollector;
use Kriss\WebmanDebugBar\DataCollector\TimeDataCollector;
use Kriss\WebmanDebugBar\DataCollector\WebmanCollector;
use Kriss\WebmanDebugBar\Helper\ArrayHelper;
use Kriss\WebmanDebugBar\Helper\StringHelper;
use Kriss\WebmanDebugBar\Traits\DebugBarOverwrite;
use Webman\Http\Request;
use Webman\Route;

class WebmanDebugBar extends DebugBar
{
    use DebugBarOverwrite;

    protected array $config = [
        'enable' => false,
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
            'db' => []
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->config = ArrayHelper::merge($this->config, $config);

        if ($this->isEnable()) {
            $this->boot();
        }
    }

    public function isEnable(): bool
    {
        return $this->config['enable'];
    }

    protected bool $booted = false;

    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // 启用 session 支持
        if ($this->config['http_driver']) {
            if ($this->config['http_driver'] === true) {
                $this->config['http_driver'] = function () {
                    return new WebmanHttpDriver();
                };
            }
            $httpDriver = call_user_func($this->config['http_driver']);
            $this->setHttpDriver($httpDriver);
        }
        // 存储
        if ($this->config['storage']) {
            if ($this->config['storage'] === true) {
                $this->config['storage'] = function () {
                    $path = runtime_path() . '/debugbar';
                    return new FileStorage($path);
                };
            }
            $storage = call_user_func($this->config['storage']);
            $this->setStorage($storage);
        }
        // Collector
        $collectors = [
            10 => new PhpInfoCollector(),
            20 => new WebmanCollector(),
            30 => new MessagesCollector(),
            40 => new TimeDataCollector(),
            50 => new MemoryCollector(),
            60 => new ExceptionsCollector(),
            70 => new RequestDataCollector(),
        ];
        if (class_exists('Illuminate\Database\Capsule\Manager')) {
            $collectors[80] = new LaravelQueryCollector($this->config['options']['db'], $collectors[40]);
        }
        if ($this->config['collectors']) {
            $this->config['collectors'] = call_user_func($this->config['collectors'], $collectors);
            $collectors = $this->config['collectors'] + $collectors;
        }
        ksort($collectors);
        foreach ($collectors as $collector) {
            $this->addCollector($collector);
        }
        // renderer
        $renderer = $this->getJavascriptRenderer($this->config['asset_base_url']);
        // 历史访问
        if ($this->getStorage() && $this->config['open_handler_url']) {
            $renderer->setOpenHandlerUrl($this->config['open_handler_url']);
        }
        // laravel query 的配置
        if ($this->hasCollector('query') && $this->getCollector('query') instanceof LaravelQueryCollector) {
            $renderer->addAssets([], [
                __DIR__ . '/Laravel/Resources/sqlqueries2/widget.js',
            ]);
        }
        // ajax 绑定
        $renderer->setBindAjaxHandlerToXHR();
        $renderer->setBindAjaxHandlerToFetch();
        $renderer->setBindAjaxHandlerToJquery();
        // 其他配置参数
        $renderer->setOptions($this->config['javascript_renderer_options']);

        $this->booted = true;
    }

    public function registerRoute(): void
    {
        if (!$this->isEnable()) {
            return;
        }

        // 示例的路由
        if ($this->config['sample_url']) {
            Route::get($this->config['sample_url'], function () {
                return response("<html lang='en'><body><h1>DebugBar Sample</h1></body></html>");
            });
        }
        // 历史记录的路由
        if ($this->config['open_handler_url']) {
            Route::get($this->config['open_handler_url'], function () {
                $openHandler = new OpenHandler($this);
                $data = $openHandler->handle(request()->get(), false, true);
                return response($data);
            });
        }
        // 静态资源路由
        $renderer = $this->getJavascriptRenderer();
        Route::get($renderer->getBaseUrl() . '/[{path:.+}]', function ($request, $path = '') use ($renderer) {
            // 静态文件目录
            $staticBasePath = $renderer->getBasePath();
            // 安全检查，避免url里 /../../../password 这样的非法访问
            if (strpos($path, '..') !== false) {
                return response('<h1>400 Bad Request</h1>', 400);
            }
            // 特殊的文件
            if ($path === 'widgets/sqlqueries/widget.js' && $this->hasCollector('queries') && $this->getCollector('queries') instanceof LaravelQueryCollector) {
                $file = __DIR__ . '/Laravel/Resources/sqlqueries/widget.js';
                return response()->withFile($file);
            }
            // 文件
            $file = "$staticBasePath/$path";
            if (!is_file($file)) {
                return response('<h1>404 Not Found</h1>', 404);
            }
            return response()->withFile($file);
        });
    }

    public function isSkipRequest(Request $request): bool
    {
        if ($this->config['skip_request_path']) {
            $path = $request->path();
            foreach ($this->config['skip_request_path'] as $pathPattern) {
                if (StringHelper::matchWildcard($pathPattern, $path)) {
                    return true;
                }
            }
        }
        if ($this->config['skip_request_callback']) {
            if (call_user_func($this->config['skip_request_callback'], $request)) {
                return true;
            }
        }
        return false;
    }
}