<?php

namespace WebmanTech\Debugbar\Ext;

use Webman\Http\Request;
use Webman\Http\Response;

class HttpExt
{
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Response
     */
    protected $response;

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

        $header = $this->request->header('accept');
        if (is_array($header)) {
            $header = implode(', ', $header);
        }
        return str_contains((string)$header, 'text/html');
    }

    /**
     * 响应的是否是 html
     * @return bool
     */
    public function isHtmlResponse(): bool
    {
        if ($header = $this->response->getHeader('Content-Type')) {
            if (is_array($header)) {
                $header = implode(';', $header);
            }
            $is = str_contains($header, 'text/html');
            if ($is) {
                return true;
            }
        }
        if (str_starts_with($this->response->rawBody(), '<html') || str_contains($this->response->rawBody(), '<body')) {
            return true;
        }
        return false;
    }
}
