<?php

namespace Kriss\WebmanDebugBar\Ext;

use Webman\Http\Request;
use Webman\Http\Response;

class HttpExt
{
    protected Request $request;
    protected Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * 是否是重定向
     * @return bool
     */
    public function isRedirection(): bool
    {
        return $this->response->getStatusCode() >= 300 && $this->response->getStatusCode() < 400;
    }

    /**
     * 是否 accept html
     * @return bool
     */
    public function isHtmlAccepted(): bool
    {
        if ($this->request->isAjax()) {
            return false;
        }

        $header = $this->request->header('Accept');
        if (is_array($header)) {
            $header = implode(', ', $header);
        }
        $is = strpos($header, 'text/html') !== false;
        if ($is) {
            return true;
        }

        return false;
    }

    /**
     * 响应的是否是 html
     * @return bool
     */
    public function isHtmlResponse(): bool
    {
        $header = $this->response->getHeader('Content-Type');
        if (is_array($header)) {
            $header = implode(';', $header);
        }
        $is = strpos($header, 'text/html') !== false;
        if ($is) {
            return true;
        }
        if (strpos($this->response->rawBody(), '<html') === 0 || strpos($this->response->rawBody(), '<body') !== false) {
            return true;
        }
        return false;
    }
}