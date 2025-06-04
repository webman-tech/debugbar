<?php

namespace WebmanTech\Debugbar\DataCollector;

use DebugBar\DataCollector\RequestDataCollector as OriginRequestDataCollector;
use Webman\Http\Request;
use Webman\Http\Response;

class RequestDataCollector extends OriginRequestDataCollector
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
     * @inheritdoc
     */
    public function collect()
    {
        $request = $this->request;
        $response = $this->response;
        $data = [
            'app' => $request->app,
            'controller' => $request->controller,
            'action' => $request->action,
            'method' => $request->method(),
            'host' => $request->host(),
            'uri' => $request->uri(),
            'path' => $request->path(),
            'get' => $request->get(),
            'post' => $request->post(),
            'header' => $request->header(),
            'cookie' => $request->cookie(),
            'session' => $request->session()->all(),
            'server' => $_SERVER,
            'response_status_code' => $response->getStatusCode(),
            'response_reason_phrase' => $response->getReasonPhrase(),
            'response_headers' => $response->getHeaders(),
        ];
        return array_map(function ($item) {
            if ($this->isHtmlVarDumperUsed()) {
                $item = $this->getVarDumper()->renderVar($item);
            } else {
                $item = $this->getDataFormatter()->formatVar($item);
            }
            return $item;
        }, $data);
    }
}