<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\HttpDriverInterface;
use Webman\Http\Request;
use Webman\Http\Response;

class WebmanHttpDriver implements HttpDriverInterface
{
    protected Request $request;
    protected Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @inheritDoc
     */
    function setHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->response->header($key, $value);
        }
    }

    /**
     * @inheritDoc
     */
    function isSessionStarted()
    {
        return !!$this->request->session();
    }

    /**
     * @inheritDoc
     */
    function setSessionValue($name, $value)
    {
        $this->request->session()->set($name, $value);
    }

    /**
     * @inheritDoc
     */
    function hasSessionValue($name)
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
    function deleteSessionValue($name)
    {
        $this->request->session()->delete($name);
    }
}