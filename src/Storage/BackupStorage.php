<?php

namespace Arpan\DatabaseDumper\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

class BackupStorage
{
    protected $disk;

    public function __construct(Filesystem $disk)
    {
        $this->disk = $disk;
    }

    public function store($localPath, $remotePath)
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException(
                'Unable to open database dump file.'
            );
        }

        try {
            $stored = $this->disk->put(
                $remotePath,
                $stream
            );

            if (!$stored) {
                throw new \RuntimeException(
                    'Unable to store database dump.'
                );
            }

            return $remotePath;
        } finally {
            fclose($stream);
        }
    }

    public function delete($path)
    {
        return $this->disk->delete($path);
    }

    public function exists($path)
    {
        return $this->disk->exists($path);
    }

    public function files($directory)
    {
        return $this->disk->files($directory);
    }

    public function cleanupOldDumps($directory, $maxDumps)
    {
        if ($maxDumps === -1) {
            return;
        }
        if ($maxDumps < 1) {
            throw new \RuntimeException('db-dumper.max_dumps must be -1 or a positive integer.');
        }
        $files = $this->disk->files($directory);
        if (!$files) {
            return;
        }
        $files = array_filter($files, function ($file) {
            return strpos(basename($file), 'dump-') === 0;
        });
        if (!$files) {
            return;
        }
        rsort($files, SORT_STRING);
        $filesToDelete = array_slice($files, $maxDumps);
        foreach ($filesToDelete as $file) {
            $this->disk->delete($file);
        }
    }
}
