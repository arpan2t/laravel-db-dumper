<?php

namespace Arpan\DatabaseDumper\Console\Commands;

use Arpan\DatabaseDumper\Database\DatabaseDumper;
use Illuminate\Console\Command;

class DumpDatabaseCommand extends Command
{

    protected $signature = 'db:dump {--database=mysql : Database driver to dump}';
    protected $description = 'Dumping the Mysql database';
    protected $dumper;

    public function __construct(DatabaseDumper $dumper)
    {
        parent::__construct();

        $this->dumper = $dumper;
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


        $files = glob($directory . DIRECTORY_SEPARATOR . 'dump-*.sql');

        if (!$files) {
            return;
        }

        usort($files, function ($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        $filesToDelete = array_slice(
            $files,
            0,
            max(0, count($files) - $maxDumps)
        );

        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }


    public function handle()
    {

        $database = $this->option('database');

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

        $directory = config('db-dumper.path');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'dump-' . date('Y-m-d-H-i-s') . '.sql';

        $outputPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $this->dumper->dump($config, $outputPath);

            $this->cleanupOldDumps($directory);

            $this->info('Database dump completed successfully.');

            $this->line('File: ' . $outputPath);

            return 0;
        } catch (\Exception $e) {
            $this->error('Database dump failed.');

            $this->error($e->getMessage());

            return 1;
        }
    }
}
