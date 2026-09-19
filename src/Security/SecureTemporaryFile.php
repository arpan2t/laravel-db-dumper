<?php

namespace Arpan\DatabaseDumper\Security;

class SecureTemporaryFile
{
    protected $path;

    public function create($prefix, $contents)
    {
        $this->path = tempnam(
            sys_get_temp_dir(),
            $prefix
        );

        if ($this->path === false) {
            throw new \RuntimeException(
                'Unable to create temporary credential file.'
            );
        }

        try {
            if (file_put_contents($this->path, $contents) === false) {
                throw new \RuntimeException(
                    'Unable to write temporary credential file.'
                );
            }

            chmod($this->path, 0600);

            return $this->path;

        } catch (\Exception $e) {
            $this->delete();

            throw $e;
        }
    }

    public function delete()
    {
        if ($this->path && file_exists($this->path)) {
            unlink($this->path);
        }

        $this->path = null;
    }
}