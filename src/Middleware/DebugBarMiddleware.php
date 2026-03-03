<?php

namespace WebmanTech\Debugbar\Middleware;

use WeakMap;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use WebmanTech\Debugbar\DebugBar;

class DebugBarMiddleware implements MiddlewareInterface
{
    /**
     * @var WeakMap<object, callable>
     */
    private static WeakMap $eventsStart;
    /**
     * @var WeakMap<object, callable>
     */
    private static WeakMap $eventsEnd;

    /**
     * @inheritDoc
     */
    public function process(Request $request, callable $handler): Response
    {
        self::initEvents();

        $debugBar = DebugBar::instance();
        if ($debugBar->isSkipRequest($request)) {
            return $handler($request);
        }

        foreach (self::$eventsStart as $cb) {
            $cb($request);
        }

        $debugBar->boot();
        $debugBar->startMeasure(static::class, 'Application');

        /** @var Response $response */
        $response = $handler($request);

        $debugBar->stopMeasure(static::class);

        $response = $debugBar->modifyResponse($request, $response);

        foreach (self::$eventsEnd as $cb) {
            $cb($request, $response);
        }

        return $response;
    }

    private static function initEvents(): void
    {
        if (!isset(self::$eventsStart)) {
            self::$eventsStart = new WeakMap();
        }
        if (!isset(self::$eventsEnd)) {
            self::$eventsEnd = new WeakMap();
        }
    }

    public static function bindEventWhenRequestStart(object $collector, callable $cb): void
    {
        self::initEvents();
        self::$eventsStart[$collector] = $cb;
    }

    public static function bindEventWhenRequestEnd(object $collector, callable $cb): void
    {
        self::initEvents();
        self::$eventsEnd[$collector] = $cb;
    }
}
