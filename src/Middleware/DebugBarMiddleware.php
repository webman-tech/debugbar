<?php

namespace WebmanTech\Debugbar\Middleware;

use WebmanTech\Debugbar\DebugBar;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

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

        $debugBar->boot();
        $debugBar->startMeasure(static::class, 'Application');

        /** @var Response $response */
        $response = $handler($request);

        $debugBar->stopMeasure(static::class);

        return $debugBar->modifyResponse($request, $response);
    }
}