<?php

namespace WebmanTech\Debugbar\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use WebmanTech\Debugbar\DebugBar;

class DebugBarMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(Request $request, callable $handler): Response
    {
        $debugBar = DebugBar::instance();
        if ($debugBar->isSkipRequest($request)) {
            return $handler($request);
        }

        foreach (self::$events['start'] as $cb) {
            $cb($request);
        }

        $debugBar->boot();
        $debugBar->startMeasure(static::class, 'Application');

        /** @var Response $response */
        $response = $handler($request);

        $debugBar->stopMeasure(static::class);

        foreach (self::$events['end'] as $cb) {
            $cb($request, $response);
        }

        return $debugBar->modifyResponse($request, $response);
    }

    private static array $events = [
        'start' => [],
        'end' => [],
    ];

    public static function bindEventWhenRequestStart(callable $cb): void
    {
        self::$events['start'][] = $cb;
    }

    public static function bindEventWhenRequestEnd(callable $cb): void
    {
        self::$events['end'][] = $cb;
    }
}
