<?php

namespace Arpan\DatabaseDumper\Console\Commands;

use Arpan\DatabaseDumper\Compression\BackupCompressor;
use Arpan\DatabaseDumper\Database\DatabaseDumper;
use Arpan\DatabaseDumper\Storage\BackupStorage;
use Illuminate\Console\Command;
use PSpell\Config;

class DumpDatabaseCommand extends Command
{

    protected $signature = 'db:dump {--database=mysql : Database driver to dump}';
    protected $description = 'Dumping the Mysql database';
    protected $dumper;
    protected $storage;
    protected $compressor;

    public function __construct(DatabaseDumper $dumper, BackupStorage $storage, BackupCompressor $compressor)
    {
        parent::__construct();

        $this->dumper = $dumper;
        $this->storage = $storage;
        $this->compressor = $compressor;
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

        $compress = config('db-dumper.compress', false);

        $directory = trim(config('db-dumper.path', 'database-dumps'), '/');

        $extension = $this->dumper->getExtension($database);

        $filename = 'dump-' . date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $extension;

        if ($compress === true) {
            $filename = $filename . '.zip';
        }
        $storagePath = $directory ? $directory . '/' . $filename : $filename;

        $temporarySqlPath = null;
        $temporaryZipPath = null;

        try {

            $temporarySqlPath = tempnam(sys_get_temp_dir(), 'db-dumper-');

            if ($temporarySqlPath === false) {
                throw new \RuntimeException(
                    'Unable to create temporary SQL file.'
                );
            }
            $this->dumper->dump($database, $temporarySqlPath);

            $temporaryPath = $temporarySqlPath;

            if ($compress) {
                $temporaryZipPath = tempnam(sys_get_temp_dir(), 'db-dumper-');
                if ($temporaryZipPath === false) {
                    throw new \RuntimeException(
                        'Unable to create temporary ZIP file.'
                    );
                }
                $this->compressor->compress(
                    $temporarySqlPath,
                    $temporaryZipPath
                );

                $temporaryPath = $temporaryZipPath;
            }

            $this->storage->store($temporaryPath, $storagePath);

            $this->storage->cleanupOldDumps($directory, config('db-dumper.max_dumps'));

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
