<?php

namespace Kriss\WebmanDebugBar\Middleware;

use DebugBar\JavascriptRenderer;
use Kriss\WebmanDebugBar\DebugBar;
use Kriss\WebmanDebugBar\WebmanDebugBar;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 参考：https://github.com/php-middleware/phpdebugbar/blob/master/src/PhpDebugBarMiddleware.php
 */
class DebugBarMiddleware implements MiddlewareInterface
{
    protected ?WebmanDebugBar $debugBar;
    protected JavascriptRenderer $debugBarRenderer;

    /**
     * @inheritDoc
     */
    public function process(Request $request, callable $handler): Response
    {
        $this->debugBar = DebugBar::instance();
        if (!$this->debugBar->isEnable()) {
            return $handler($request);
        }

        $response = $handler($request);

        $this->debugBarRenderer = $this->debugBar->getJavascriptRenderer();

        if ($this->shouldReturnResponse($request, $response)) {
            return $response;
        }

        return $this->attachDebugBarToHtmlResponse($response);
    }

    protected function shouldReturnResponse(Request $request, Response $response): bool
    {
        if ($this->isRedirect($response)) {
            return true;
        }
        if (!$this->isHtmlAccepted($request)) {
            return true;
        }
        if (!$this->isHtmlResponse($response)) {
            return true;
        }

        return false;
    }

    protected function attachDebugBarToHtmlResponse(Response $response): Response
    {
        $head = $this->debugBarRenderer->renderHead();
        $body = $this->debugBarRenderer->render();
        $responseBody = $response->rawBody();
        $response->withBody($responseBody . $head . $body);

        return $response;
    }

    protected function isHtmlAccepted(Request $request): bool
    {
        $header = $request->header('Accept');
        if (is_array($header)) {
            $header = implode(', ', $header);
        }
        $is = strpos($header, 'application/json') !== false;
        if ($is) {
            return true;
        }

        return false;
    }

    protected function isHtmlResponse(Response $response): bool
    {
        $header = $response->getHeader('Content-Type');
        if (is_array($header)) {
            $header = implode(';', $header);
        }
        $is = strpos($header, 'text/html') !== false;
        if ($is) {
            return true;
        }
        if (strpos($response->rawBody(), '<html') === 0 || strpos($response->rawBody(), '<body>') !== false) {
            return true;
        }
        return false;
    }

    protected function isRedirect(Response $response): bool
    {
        $statusCode = $response->getStatusCode();

        return $statusCode >= 300 && $statusCode < 400 && $response->getHeader('Location') !== '';
    }
}