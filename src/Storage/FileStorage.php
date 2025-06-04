<?php

namespace WebmanTech\Debugbar\Storage;

/**
 * @see https://github.com/maximebf/php-debugbar/pull/527
 * 后续移除后记得修改 AutoCleanFileStorage 的继承
 */
class FileStorage extends \DebugBar\Storage\FileStorage
{
    /**
     * {@inheritdoc}
     */
    public function find(array $filters = array(), $max = 20, $offset = 0)
    {
        //Loop through all .json files and remember the modified time and id.
        $files = array();
        foreach (new \DirectoryIterator($this->dirname) as $file) {
            if ($file->getExtension() == 'json') {
                $files[] = array(
                    'time' => $file->getMTime(),
                    'id' => $file->getBasename('.json')
                );
            }
        }

        //Sort the files, newest first
        usort($files, function ($a, $b) {
            return $a['time'] < $b['time'] ? 1 : -1;
        });

        //Load the metadata and filter the results.
        $results = array();
        $i = 0;
        foreach ($files as $file) {
            //When filter is empty, skip loading the offset
            if ($i++ < $offset && empty($filters)) {
                $results[] = null;
                continue;
            }
            $data = $this->get($file['id']);
            $meta = $data['__meta'];
            unset($data);
            if ($this->filter($meta, $filters)) {
                $results[] = $meta;
            }
            if (count($results) >= ($max + $offset)) {
                break;
            }
        }

        return array_slice($results, $offset, $max);
    }
}