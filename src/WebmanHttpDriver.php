<?php

namespace Kriss\WebmanDebugBar;

use DebugBar\HttpDriverInterface;

class WebmanHttpDriver implements HttpDriverInterface
{
    /**
     * @inheritDoc
     */
    function setHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            response()->header($key, $value);
        }
    }

    /**
     * @inheritDoc
     */
    function isSessionStarted()
    {
        return !!request()->session();
    }

    /**
     * @inheritDoc
     */
    function setSessionValue($name, $value)
    {
        request()->session()->set($name, $value);
    }

    /**
     * @inheritDoc
     */
    function hasSessionValue($name)
    {
        return request()->session()->has($name);
    }

    /**
     * @inheritDoc
     */
    function getSessionValue($name)
    {
        return request()->session()->get($name);
    }

    /**
     * @inheritDoc
     */
    function deleteSessionValue($name)
    {
        request()->session()->delete($name);
    }
}