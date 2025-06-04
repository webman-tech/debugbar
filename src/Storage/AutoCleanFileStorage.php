<?php

namespace WebmanTech\Debugbar\Storage;

use Symfony\Component\Finder\Finder;

class AutoCleanFileStorage extends FileStorage
{
    /**
     * @var array
     */
    protected $config = [
        'gc_percent_by_files_count' => 50,
        'files_count_max' => 1000,
        'files_count_keep' => 400,
        'gc_percent_by_hours' => 10,
        'hours_keep' => 24,
    ];

    public function __construct($dirname, array $config = [])
    {
        if (!class_exists(Finder::class)) {
            throw new \InvalidArgumentException('必须先安装 symfony/finder');
        }

        parent::__construct($dirname);
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @inheritDoc
     */
    public function save($id, $data)
    {
        $this->autoClean();
        parent::save($id, $data);
    }

    private function autoClean()
    {
        if (!is_dir($this->dirname)) {
            return;
        }

        $gcPercent = random_int(0, 100);
        if ($gcPercent < $this->config['gc_percent_by_files_count']) {
            $this->cleanByDirMaxSize($this->config['files_count_max'], $this->config['files_count_keep']);
        }
        if ($gcPercent < $this->config['gc_percent_by_hours']) {
            $this->cleanByKeepHours($this->config['hours_keep']);
        }
    }

    private function cleanByDirMaxSize(int $filesCountMax, int $filesCountKeep)
    {
        $finder = Finder::create()->files()->name('*.json')->in($this->dirname);
        if (($totalCount = $finder->count()) > $filesCountMax) {
            foreach ($finder->sortByModifiedTime() as $file) {
                unlink($file->getRealPath());
                $totalCount--;
                if ($totalCount <= $filesCountKeep) {
                    break;
                }
            }
        }
    }

    private function cleanByKeepHours(int $hoursKeep)
    {
        foreach (Finder::create()->files()->name('*.json')->date('< ' . $hoursKeep . ' hour ago')->in(
            $this->dirname
        ) as $file) {
            unlink($file->getRealPath());
        }
    }
}
