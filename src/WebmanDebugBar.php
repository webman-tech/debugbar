<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use DebugBar\Storage\FileStorage;
use Kriss\WebmanDebugBar\DataCollector\LaravelQueryCollector;
use Kriss\WebmanDebugBar\DataCollector\LaravelRedisCollector;
use Kriss\WebmanDebugBar\DataCollector\MemoryCollector;
use Kriss\WebmanDebugBar\DataCollector\PhpInfoCollector;
use Kriss\WebmanDebugBar\DataCollector\RequestDataCollector;
use Kriss\WebmanDebugBar\DataCollector\RouteCollector;
use Kriss\WebmanDebugBar\DataCollector\SessionCollector;
use Kriss\WebmanDebugBar\DataCollector\TimeDataCollector;
use Kriss\WebmanDebugBar\DataCollector\WebmanCollector;
use Kriss\WebmanDebugBar\Helper\ArrayHelper;
use Kriss\WebmanDebugBar\Helper\StringHelper;
use Kriss\WebmanDebugBar\Storage\AutoCleanFileStorage;
use Kriss\WebmanDebugBar\Traits\DebugBarOverwrite;
use Webman\Http\Request;
use Webman\Route;

class WebmanDebugBar extends DebugBar
{
    use DebugBarOverwrite;

    protected array $config = [
        'enable' => false,
        'http_driver' => true, // 定义 http_driver
        'storage' => true, // 定义 storage
        'open_handler_url' => '/_debugbar/open', // storage 启用时打开历史的路由
        'asset_base_url' => '/_debugbar/assets', // 静态资源的路由
        'sample_url' => '/_debugbar/sample', // 示例页面，可用于查看 debugbar 信息，设为 null 关闭
        'javascript_renderer_options' => [], // 其他 javascriptRenderer 参数
        /**
         * 需要忽略的请求路由
         */
        'skip_request_path' => [
            '/_debugbar/open',
            '/_debugbar/assets/*',
            '*.css',
            '*.js',
        ],
        /**
         * 需要忽略的请求 callback 处理, function(Request $request): bool {}
         */
        'skip_request_callback' => null,
        /**
         * 支持的 collectors，可以配置成 class 或者 callback
         */
        'collectors' => [
            'phpinfo',
            'webman',
            'messages',
            'exceptions',
            'time',
            'memory',
            'request',
            'route',
            'session',
            'laravelDB',
            'laravelRedis',
        ],
        'options' => [
            /**
             * @see LaravelQueryCollector::$config
             */
            'db' => [],
            /**
             * @see LaravelRedisCollector::$config
             */
            'redis' => [],
        ],
    ];

    public function __construct(array $config = [])
    {
        $collectors = $config['collectors'] ?? $this->config['collectors'];
        $this->config = ArrayHelper::merge($this->config, $config);
        $this->config['collectors'] = $collectors;

        if ($this->isEnable()) {
            $this->boot();
        }
    }

    /**
     * 是否被启用
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->config['enable'];
    }

    protected bool $booted = false;

    /**
     * 加载
     */
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
                    if (class_exists('Symfony\Component\Finder\Finder')) {
                        return new AutoCleanFileStorage($path);
                    }
                    // 使用该存储形式会导致文件数量极多
                    return new FileStorage($path);
                };
            }
            $storage = call_user_func($this->config['storage']);
            $this->setStorage($storage);
        }
        // Collector
        $this->bootCollectors();
        // renderer
        $this->bootJavascriptRenderer();

        $this->booted = true;
    }

    /**
     * boot collector
     */
    protected function bootCollectors(): void
    {
        $collectorMaps = $this->collectorMaps();
        foreach ($this->config['collectors'] as $collector) {
            if (is_string($collector)) {
                if (isset($collectorMaps[$collector])) {
                    $collector = $collectorMaps[$collector];
                }
            }
            if (is_string($collector) && strpos($collector, '\\') !== false && class_exists($collector)) {
                $collector = new $collector;
            }
            if (is_callable($collector)) {
                $collector = call_user_func($collector);
            }

            if (!$collector instanceof DataCollectorInterface) {
                continue;
            }
            if ($this->hasCollector($collector->getName())) {
                continue;
            }
            $this->addCollector($collector);
        }
    }

    /**
     * 默认的 collector 配置
     * @return array
     */
    protected function collectorMaps(): array
    {
        return [
            'webman' => WebmanCollector::class,
            'phpinfo' => PhpInfoCollector::class,
            'messages' => MessagesCollector::class,
            'time' => TimeDataCollector::class,
            'memory' => MemoryCollector::class,
            'exceptions' => ExceptionsCollector::class,
            'request' => RequestDataCollector::class,
            'route' => RouteCollector::class,
            'session' => function () {
                if (request() && request()->session()) {
                    return new SessionCollector();
                }
                return null;
            },
            'laravelDB' => function () {
                if (class_exists('Illuminate\Database\Capsule\Manager')) {
                    $timeDataCollector = null;
                    if ($this->hasCollector('time')) {
                        /** @var \DebugBar\DataCollector\TimeDataCollector $timeDataCollector */
                        $timeDataCollector = $this->getCollector('time');
                    }
                    return new LaravelQueryCollector($this->config['options']['db'] ?? [], $timeDataCollector);
                }
                return null;
            },
            'laravelRedis' => function () {
                if (class_exists('Illuminate\Redis\RedisManager')) {
                    $timeDataCollector = null;
                    if ($this->hasCollector('time')) {
                        /** @var \DebugBar\DataCollector\TimeDataCollector $timeDataCollector */
                        $timeDataCollector = $this->getCollector('time');
                    }
                    return new LaravelRedisCollector($this->config['options']['redis'] ?? [], $timeDataCollector);
                }
                return null;
            },
        ];
    }

    /**
     * @inheritDoc
     */
    public function getJavascriptRenderer($baseUrl = null, $basePath = null)
    {
        if ($this->jsRenderer === null) {
            $this->jsRenderer = new WebmanJavascriptRenderer($this, $baseUrl, $basePath);
        }
        return $this->jsRenderer;
    }

    /**
     * boot javascriptRenderer
     */
    protected function bootJavascriptRenderer(): void
    {
        $renderer = $this->getJavascriptRenderer($this->config['asset_base_url']);
        // 历史访问
        if ($this->getStorage() && $this->config['open_handler_url']) {
            $renderer->setOpenHandlerUrl($this->config['open_handler_url']);
        }
        // 其他配置参数
        $renderer->setOptions($this->config['javascript_renderer_options']);
    }

    /**
     * 注册必要路由
     */
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
        if ($renderer instanceof WebmanJavascriptRenderer) {
            $renderer->registerAssetRoute();
        }
    }

    /**
     * 是否是需要忽略的请求
     * @param Request $request
     * @return bool
     */
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