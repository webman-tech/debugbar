<?php

namespace WebmanTech\Debugbar;

use Closure;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DebugBar;
use DebugBar\OpenHandler;
use DebugBar\Storage\StorageInterface;
use WebmanTech\Debugbar\DataCollector\LaravelQueryCollector;
use WebmanTech\Debugbar\DataCollector\LaravelRedisCollector;
use WebmanTech\Debugbar\DataCollector\MemoryCollector;
use WebmanTech\Debugbar\DataCollector\PhpInfoCollector;
use WebmanTech\Debugbar\DataCollector\RequestDataCollector;
use WebmanTech\Debugbar\DataCollector\RouteCollector;
use WebmanTech\Debugbar\DataCollector\SessionCollector;
use WebmanTech\Debugbar\DataCollector\ThinkPdoCollector;
use WebmanTech\Debugbar\DataCollector\TimeDataCollector;
use WebmanTech\Debugbar\DataCollector\WebmanCollector;
use WebmanTech\Debugbar\Ext\HttpExt;
use WebmanTech\Debugbar\Helper\ArrayHelper;
use WebmanTech\Debugbar\Helper\StringHelper;
use WebmanTech\Debugbar\Storage\AutoCleanFileStorage;
use WebmanTech\Debugbar\Storage\FileStorage;
use WebmanTech\Debugbar\Traits\DebugBarOverwrite;
use support\Container;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Route;

class WebmanDebugBar extends DebugBar
{
    use DebugBarOverwrite;

    /**
     * @var array
     */
    protected $config = [
        'enabled' => true, // 弃用
        'storage' => true, // 定义 storage
        'http_driver' => true, // 定义 http_driver
        'open_handler_url' => '/_debugbar/open', // storage 启用时打开历史的路由
        'open_handler_url_make' => null, // 构建用于访问的 open_handler 的 url 地址，callable 类型，用于二级目录访问的场景
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
        'collectors_boot' => [
            'phpinfo' => true,
            'webman' => true,
            'messages' => true,
            'exceptions' => true,
            'time' => true,
            'memory' => true,
            'route' => true,
            'laravelDB' => true,
            'thinkDb' => true,
            'laravelRedis' => true,
        ],
        'collectors_response' => [
            'request' => true,
            'session' => true,
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
        $this->config = ArrayHelper::merge($this->config, $config);
    }

    private static $staticCache = [];

    private function getOrSetStaticCache(string $key, callable $fn)
    {
        if (!isset(static::$staticCache[$key])) {
            static::$staticCache[$key] = $fn();
        }
        return static::$staticCache[$key];
    }

    /**
     * @var bool
     */
    protected $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // 存储
        if ($this->config['storage']) {
            $this->setStorage($this->getOrSetStaticCache('storage', function (): StorageInterface {
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
                return call_user_func($this->config['storage']);
            }));
        }
        // Collector
        $collectorMaps = $this->collectorMaps();
        foreach ($this->config['collectors_boot'] as $name => $builder) {
            $collector = $this->getOrSetStaticCache('collector_' . $name, function () use ($builder, $name, $collectorMaps) {
                if ($builder === false) {
                    return null;
                }
                if ($builder === true && isset($collectorMaps[$name])) {
                    $builder = $collectorMaps[$name];
                }
                if ($collector = $this->buildCollector($builder)) {
                    return $collector;
                }
                return null;
            });
            if ($collector instanceof DataCollectorInterface) {
                $this->addCollector($collector);
            }
        }
        // javascriptRenderer
        $this->bootJavascriptRenderer();

        $this->booted = true;
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
            'route' => RouteCollector::class,
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
            'request' => function (Request $request, Response $response) {
                return new RequestDataCollector($request, $response);
            },
            'session' => function (Request $request) {
                if (request() && request()->session()) {
                    return new SessionCollector($request);
                }
                return null;
            },
            'thinkDb' => function () {
                if (class_exists('think\facade\Db')) {
                    $timeDataCollector = null;
                    if ($this->hasCollector('time')) {
                        /** @var \DebugBar\DataCollector\TimeDataCollector $timeDataCollector */
                        $timeDataCollector = $this->getCollector('time');
                    }
                    return new ThinkPdoCollector(config('thinkorm', []), $timeDataCollector);
                }
                return null;
            }
        ];
    }

    /**
     * @param string|callable $collector
     * @param mixed ...$params
     * @return DataCollectorInterface|null
     */
    protected function buildCollector($collector, ...$params): ?DataCollectorInterface
    {
        if (is_string($collector) && strpos($collector, '\\') !== false && class_exists($collector)) {
            $collector = new $collector;
        }
        if (is_callable($collector)) {
            $collector = call_user_func($collector, ...$params);
        }
        if (!$collector instanceof DataCollectorInterface) {
            return null;
        }

        return $collector;
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
            $url = $this->config['open_handler_url'];
            if (is_callable($this->config['open_handler_url_make'])) {
                $url = call_user_func($this->config['open_handler_url_make'], $url);
            }
            $renderer->setOpenHandlerUrl($url);
        }
        // 其他配置参数
        $renderer->setOptions($this->config['javascript_renderer_options']);
    }

    /**
     * 注册必要路由
     */
    public function registerRoute(): void
    {
        $this->boot();

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

    public function modifyResponse(Request $request, Response $response): Response
    {
        // handle exception
        if ($exception = $response->exception()) {
            $this->addThrowable($exception);
        }
        // 启用 session 支持
        if ($this->config['http_driver']) {
            if ($this->config['http_driver'] === true) {
                $this->config['http_driver'] = new WebmanHttpDriver($request, $response);
            }
            if (is_callable($this->config['http_driver'])) {
                $this->config['http_driver'] = call_user_func($this->config['http_driver']);
            }
            $this->setHttpDriver($this->config['http_driver']);
        }
        // 添加 collectors
        $collectorMaps = $this->collectorMaps();
        foreach ($this->config['collectors_response'] as $name => $builder) {
            if ($builder === false) {
                continue;
            }
            if ($builder === true) {
                $builder = $collectorMaps[$name];
            }
            if ($collector = $this->buildCollector($builder, $request, $response)) {
                $this->addCollector($collector);
            }
        }
        // 处理 response
        /** @var HttpExt $httpExt */
        $httpExt = Container::make(HttpExt::class, ['request' => $request, 'response' => $response]);
        if ($httpExt->isRedirection()) {
            $this->stackData();
        } elseif (!$httpExt->isHtmlResponse()) {
            $this->sendDataInHeaders(true);
        } elseif ($httpExt->isHtmlAccepted()) {
            $response = $this->attachDebugBarToHtmlResponse($response);
        }

        return $response;
    }

    /**
     * @param Response $response
     * @return Response
     */
    protected function attachDebugBarToHtmlResponse(Response $response): Response
    {
        $renderer = $this->getJavascriptRenderer();
        $head = $renderer->renderHead();
        $foot = $renderer->render();
        $content = $response->rawBody();

        $pos = strripos($content, '</head>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $head . substr($content, $pos);
        } else {
            $foot = $head . $foot;
        }

        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $foot . substr($content, $pos);
        } else {
            $content = $content . $foot;
        }

        $response->withBody($content);
        $response->withoutHeader('Content-Length');

        return $response;
    }

    /**
     * @param Throwable $e
     */
    public function addThrowable(Throwable $e)
    {
        if ($this->hasCollector('exceptions')) {
            /** @var ExceptionsCollector $collector */
            $collector = $this->getCollector('exceptions');
            $collector->addThrowable($e);
        }
    }

    /**
     * @param $message
     * @param string $type
     */
    public function addMessage($message, string $type = 'info')
    {
        if ($this->hasCollector('messages')) {
            /** @var MessagesCollector $collector */
            $collector = $this->getCollector('messages');
            $collector->addMessage($message, $type);
        }
    }

    /**
     * @param string $name
     * @param string|null $label
     */
    public function startMeasure(string $name, string $label = null)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->startMeasure($name, $label);
        }
    }

    /**
     * @param string $name
     */
    public function stopMeasure(string $name)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            try {
                $collector->stopMeasure($name);
            } catch (\Exception $e) {
                $this->addThrowable($e);
            }
        }
    }

    /**
     * @param string $label
     * @param float $start
     * @param float $end
     */
    public function addMeasure(string $label, float $start, float $end)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $collector->addMeasure($label, $start, $end);
        }
    }

    /**
     * @param string $label
     * @param Closure $closure
     * @return mixed
     */
    public function measure(string $label, Closure $closure)
    {
        if ($this->hasCollector('time')) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $collector */
            $collector = $this->getCollector('time');
            $result = $collector->measure($label, $closure);
        } else {
            $result = $closure();
        }
        return $result;
    }
}