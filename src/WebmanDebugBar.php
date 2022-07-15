<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use DebugBar\Storage\FileStorage;
use Kriss\WebmanDebugBar\DataCollector\MemoryCollector;
use Kriss\WebmanDebugBar\DataCollector\RequestDataCollector;
use Kriss\WebmanDebugBar\DataCollector\TimeDataCollector;
use Kriss\WebmanDebugBar\Helper\StringHelper;
use Kriss\WebmanDebugBar\Traits\DebugBarOverwrite;
use Webman\Http\Request;
use Webman\Route;

class WebmanDebugBar extends DebugBar
{
    use DebugBarOverwrite;

    protected array $config = [
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
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);

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
        $this->addCollector(new PhpInfoCollector());
        $this->addCollector(new MessagesCollector());
        $this->addCollector(new TimeDataCollector());
        $this->addCollector(new MemoryCollector());
        $this->addCollector(new ExceptionsCollector());
        $this->addCollector(new RequestDataCollector());
        if ($this->config['collectors']) {
            $this->config['collectors'] = call_user_func($this->config['collectors']);
            foreach ($this->config['collectors'] as $collector) {
                $this->addCollector($collector);
            }
        }
        // renderer
        $renderer = $this->getJavascriptRenderer($this->config['asset_base_url']);
        // 历史访问
        if ($this->getStorage() && $this->config['open_handler_url']) {
            $renderer->setOpenHandlerUrl($this->config['open_handler_url']);
        }
        $renderer->setBindAjaxHandlerToXHR();
        $renderer->setBindAjaxHandlerToFetch();
        $renderer->setBindAjaxHandlerToJquery();
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