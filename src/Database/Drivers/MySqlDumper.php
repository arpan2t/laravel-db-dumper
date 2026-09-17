<?php

namespace Arpan\DatabaseDumper\Database\Drivers;

class MySqlDumper
{
    public function dump(array $config, $outputPath)
    {
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($outputPath)
        );
        
        $environment = $_ENV;

        $environment['MYSQL_PWD'] = isset($config['password'])
            ? $config['password']
            : '';

        exec(
            'env MYSQL_PWD=' . escapeshellarg($environment['MYSQL_PWD']) . ' ' . $command,
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'MySQL database dump failed.'
            );
        }

        return $outputPath;
    }
}
