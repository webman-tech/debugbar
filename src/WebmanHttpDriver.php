<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\HttpDriverInterface;
use Webman\Http\Response;
use Workerman\Protocols\Http\Session;

class WebmanHttpDriver implements HttpDriverInterface
{
    protected ?Session $session = null;
    protected ?Response $response = null;

    protected function session(): Session
    {
        if ($this->session === null) {
            $this->session = request()->session();
        }
        return $this->session;
    }

    protected function response(): Response
    {
        if ($this->response === null) {
            $this->response = response();
        }
        return $this->response;
    }

    /**
     * @inheritDoc
     */
    function setHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->response()->header($key, $value);
        }
    }

    /**
     * @inheritDoc
     */
    function isSessionStarted()
    {
        return !!$this->session();
    }

    /**
     * @inheritDoc
     */
    function setSessionValue($name, $value)
    {
        $this->session()->set($name, $value);
    }

    /**
     * @inheritDoc
     */
    function hasSessionValue($name)
    {
        return $this->session()->has($name);
    }

    /**
     * @inheritDoc
     */
    function getSessionValue($name)
    {
        return $this->session()->get($name);
    }

    /**
     * @inheritDoc
     */
    function deleteSessionValue($name)
    {
        $this->session()->delete($name);
    }
}