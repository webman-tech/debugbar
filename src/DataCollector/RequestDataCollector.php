<?php

namespace Kriss\WebmanDebugBar\DataCollector;

use DebugBar\DataCollector\RequestDataCollector as PhpRequestDataCollector;

class RequestDataCollector extends PhpRequestDataCollector
{
    /**
     * @inheritdoc
     */
    public function collect()
    {
        $request = request();
        $data = [
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