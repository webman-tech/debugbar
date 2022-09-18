<?php

namespace WebmanTech\Debugbar\Traits;

trait DebugBarOverwrite
{
    /**
     * 修改 $request_variables
     * @inheritdoc
     */
    public function collect()
    {
        $request = request();
        $request_variables = array(
            'method' => $request->method(),
            'uri' => $request->uri(),
            'ip' => $request->getRealIp(),
        );

        // 以下未修改

        $this->data = array(
            '__meta' => array_merge(
                array(
                    'id' => $this->getCurrentRequestId(),
                    'datetime' => date('Y-m-d H:i:s'),
                    'utime' => microtime(true)
                ),
                $request_variables
            )
        );

        foreach ($this->collectors as $name => $collector) {
            $this->data[$name] = $collector->collect();
        }

        // Remove all invalid (non UTF-8) characters
        array_walk_recursive($this->data, function (&$item) {
            if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });

        if ($this->storage !== null) {
            $this->storage->save($this->getCurrentRequestId(), $this->data);
        }

        return $this->data;
    }
}