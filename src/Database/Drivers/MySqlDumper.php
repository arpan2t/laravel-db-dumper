<?php

namespace Arpan\DatabaseDumper\Database\Drivers;

use Arpan\DatabaseDumper\Security\SecureTemporaryFile;
use Exception;

class MySqlDumper
{
    protected $temporaryFile;


    public function __construct(SecureTemporaryFile $secureTemporaryFile)
    {
        $this->temporaryFile  = $secureTemporaryFile;
    }

    public function dump(array $config, $outputPath)
    {
        $contents = "[client]\n";
        $contents .= 'host=' . $config['host'] . "\n";
        $contents .= 'port=' . $config['port'] . "\n";
        $contents .= 'user=' . $config['username'] . "\n";
        $contents .= 'password=' . $config['password'] . "\n";

        $tempFile = $this->temporaryFile->create('mysql-dumper-', $contents);

        try {
            $command = sprintf(
                'mysqldump --defaults-extra-file=%s %s > %s',
                escapeshellarg($tempFile),
                escapeshellarg($config['database']),
                escapeshellarg($outputPath)
            );

            exec($command, $output, $exitCode);


            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    'MySQL database dump failed.'
                );
            }

            return $outputPath;
        } finally {
            $this->temporaryFile->delete();
        }
    }
}
