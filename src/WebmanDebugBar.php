<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use DebugBar\Storage\FileStorage;
use Webman\Route;

class WebmanDebugBar extends DebugBar
{
    protected array $config = [
        'enable' => false,
        'collectors' => null,
        'http_driver' => true,
        'storage' => true,
        'open_handler_url' => '/_debugbar_open',
        'asset_base_url' => '/_debugbar',
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
        $renderer->setIncludeVendors();
        $renderer->setBindAjaxHandlerToFetch();
        $renderer->setBindAjaxHandlerToXHR();

        $this->booted = true;
    }

    public function registerRoute(): void
    {
        if (!$this->isEnable()) {
            return;
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
}