<?php

namespace WebmanTech\Debugbar;

use DebugBar\HttpDriverInterface;
use Webman\Http\Request;
use Webman\Http\Response;

class WebmanHttpDriver implements HttpDriverInterface
{
    public function __construct(protected Request $request, protected Response $response)
    {
    }

    /**
     * @inheritDoc
     */
    function setHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            $this->response->header($key, $value);
        }
    }

    /**
     * @inheritDoc
     */
    function isSessionStarted(): bool
    {
        return !!($this->request->context['session'] ?? null);
    }

    /**
     * @inheritDoc
     */
    function setSessionValue($name, $value): void
    {
        $this->request->session()->set($name, $value);
    }

    /**
     * @inheritDoc
     */
    function hasSessionValue($name): bool
    {
        return $this->request->session()->has($name);
    }

    /**
     * @inheritDoc
     */
    function getSessionValue($name)
    {
        return $this->request->session()->get($name);
    }

    /**
     * @inheritDoc
     */
    function deleteSessionValue($name): void
    {
        $this->request->session()->delete($name);
    }
}
