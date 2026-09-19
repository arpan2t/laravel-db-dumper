<?php

namespace Arpan\DatabaseDumper\Console\Commands;

use Arpan\DatabaseDumper\Database\DatabaseDumper;
use Arpan\DatabaseDumper\Storage\BackupStorage;
use Illuminate\Console\Command;

class DumpDatabaseCommand extends Command
{

    protected $signature = 'db:dump {--database=mysql : Database driver to dump}';
    protected $description = 'Dumping the Mysql database';
    protected $dumper;
    protected $storage;

    public function __construct(DatabaseDumper $dumper, BackupStorage $storage)
    {
        parent::__construct();

        $this->dumper = $dumper;
        $this->storage = $storage;
    }

    protected function cleanupOldDumps($directory)
    {
        $maxDumps = config('db-dumper.max_dumps');

        if ($maxDumps === -1) {
            return;
        }

        if ($maxDumps < 1) {
            throw new \RuntimeException(
                'db-dumper.max_dumps must be -1 or a positive integer.'
            );
        }


        $files = glob(
            $directory . DIRECTORY_SEPARATOR . 'dump-*.sql'
        );

        if (!$files) {
            return;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $filesToDelete = array_slice($files, $maxDumps);

        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }


    public function handle()
    {

        $database = $this->option('database') ?? "mysql";

        if ($database !== 'mysql') {
            $this->error(
                "Database driver [{$database}] is not supported."
            );

            $this->line(
                'Currently supported drivers: mysql'
            );

            return 1;
        }

        $this->info('Database dump started...');

        $config = config('database.connections.mysql');

        $directory = trim(config('db-dumper.path', 'database-dumps'), '/');

        $extension = $this->dumper->getExtension($database);

        $filename = 'dump-' . date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $extension;

        $temporaryPath = tempnam(sys_get_temp_dir(), 'db-dumper-');

        if ($temporaryPath === false) {
            $this->error('Unable to create temporary dump file.');
            return 1;
        }
        $storagePath = $directory ? $directory . '/' . $filename : $filename;


        try {
            $this->dumper->dump($database , $temporaryPath);

            $this->storage->store($temporaryPath, $storagePath);

            $this->storage->cleanupOldDumps( $directory, config('db-dumper.max_dumps') );

            $this->info('Database dump completed successfully.');

            $this->line('File: ' . $storagePath);

            return 0;
        } catch (\Exception $e) {
            $this->error('Database dump failed.');

            $this->error($e->getMessage());

            return 1;
        }
    }
}
