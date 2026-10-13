<?php

namespace Arpan\DatabaseDumper\Console\Commands;

use Arpan\DatabaseDumper\Compression\BackupCompressor;
use Arpan\DatabaseDumper\Database\DatabaseDumper;
use Arpan\DatabaseDumper\Storage\BackupStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DumpDatabaseCommand extends Command
{
    protected $signature = 'db:dump {--database=mysql : Database driver to dump}';

    protected $description = 'Dump the MySQL database';

    protected $dumper;

    protected $storage;

    protected $compressor;

    public function __construct(
        DatabaseDumper $dumper,
        BackupStorage $storage,
        BackupCompressor $compressor
    ) {
        parent::__construct();

        $this->dumper = $dumper;
        $this->storage = $storage;
        $this->compressor = $compressor;
    }

    public function handle()
    {
        $database = $this->option('database');

        if ($database !== 'mysql') {
            $this->error(
                "Database driver [{$database}] is not supported."
            );

            $this->line('Currently supported drivers: mysql');

            return 1;
        }

        $this->info('Database dump started...');

        $compress = config('db-dumper.compress', false);
        $directory = trim(
            config('db-dumper.path', 'database-dumps'),
            '/'
        );

        $extension = $this->dumper->getExtension($database);

        $filename = 'dump-'
            . date('Y-m-d-H-i-s')
            . '-'
            . uniqid()
            . '.'
            . $extension;

        if ($compress) {
            $filename .= '.zip';
        }

        $tenancyEnabled = config(
            'db-dumper.tenancy.enabled',
            false
        );

        try {
            if ($tenancyEnabled) {
                return $this->dumpTenants(
                    $database,
                    $directory,
                    $filename,
                    $compress
                );
            }

            // Existing single-database behavior.
            $connectionConfig = config(
                'database.connections.mysql'
            );

            $storagePath = $directory
                ? $directory . '/' . $filename
                : $filename;

            $this->createBackup(
                $database,
                $connectionConfig,
                $storagePath,
                $directory,
                $compress
            );

            $this->info('Database dump completed successfully.');
            $this->line('File: ' . $storagePath);

            return 0;
        } catch (\Throwable $e) {
            $this->error('Database dump failed.');
            $this->error($e->getMessage());

            return 1;
        }
    }

    /**
     * Back up all tenants permitted by the central database.
     */
    protected function dumpTenants(
        $database,
        $directory,
        $filename,
        $compress
    ) {
        $tenancy = config('db-dumper.tenancy', []);
        $columns = isset($tenancy['columns'])
            ? $tenancy['columns']
            : [];

        $requiredColumns = [
            'name',
            'host',
            'port',
            'database',
            'username',
            'password',
            'allow_backup',
        ];

        foreach ($requiredColumns as $column) {
            if (
                !isset($columns[$column])
                || $columns[$column] === ''
            ) {
                throw new \RuntimeException(
                    "Missing tenant column configuration: {$column}"
                );
            }
        }

        $tenants = DB::connection(
            $tenancy['central_connection']
        )
            ->table($tenancy['table'])
            ->where(
                $columns['allow_backup'],
                1
            )
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn(
                'No tenants are eligible for database backup.'
            );

            return 0;
        }

        $successful = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $tenantName = data_get($tenant, $columns['name']);

            if (!is_string($tenantName) && !is_numeric($tenantName)) {
                $this->error('Skipping tenant with an invalid name.');
                $failed++;

                continue;
            }

            $tenantName = (string) $tenantName;

            // Prevent tenant names from creating arbitrary directories.
            $safeTenantName = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $tenantName
            );

            if ($safeTenantName === '') {
                $this->error('Skipping tenant with an invalid name.');
                $failed++;

                continue;
            }

            $tenantDirectory = trim(
                $directory . '/' . $safeTenantName,
                '/'
            );

            $storagePath = $tenantDirectory . '/' . $filename;

            try {
                $host = data_get($tenant, $columns['host']);
                $databaseName = data_get(
                    $tenant,
                    $columns['database']
                );
                $username = data_get(
                    $tenant,
                    $columns['username']
                );

                if (
                    !is_string($host)
                    || trim($host) === ''
                    || !is_string($databaseName)
                    || trim($databaseName) === ''
                    || !is_string($username)
                    || trim($username) === ''
                ) {
                    throw new \RuntimeException(
                        'Tenant database connection details are incomplete.'
                    );
                }

                $connectionConfig = [
                    'driver' => 'mysql',
                    'host' => $host,
                    'port' => data_get(
                        $tenant,
                        $columns['port']
                    ) ?: 3306,
                    'database' => $databaseName,
                    'username' => $username,
                    'password' => data_get(
                        $tenant,
                        $columns['password']
                    ),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                ];

                $this->createBackup(
                    $database,
                    $connectionConfig,
                    $storagePath,
                    $tenantDirectory,
                    $compress
                );

                $successful++;

                $this->info(
                    "Backup completed for tenant: {$tenantName}"
                );

                $this->line('File: ' . $storagePath);
            } catch (\Throwable $e) {
                $failed++;

                $this->error(
                    "Backup failed for tenant: {$tenantName}"
                );

                $this->error($e->getMessage());
            }
        }

        $this->info(
            "Tenant backups finished. Successful: {$successful}; Failed: {$failed}."
        );

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Dump, optionally compress, store, and clean up a backup.
     */
    protected function createBackup(
        $database,
        array $connectionConfig,
        $storagePath,
        $cleanupDirectory,
        $compress
    ) {
        $temporarySqlPath = null;
        $temporaryZipPath = null;

        try {
            $temporarySqlPath = tempnam(
                sys_get_temp_dir(),
                'db-dumper-'
            );

            if ($temporarySqlPath === false) {
                throw new \RuntimeException(
                    'Unable to create temporary SQL file.'
                );
            }

            $this->dumper->dump(
                $database,
                $connectionConfig,
                $temporarySqlPath
            );

            $temporaryPath = $temporarySqlPath;

            if ($compress) {
                $temporaryZipPath = tempnam(
                    sys_get_temp_dir(),
                    'db-dumper-'
                );

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

            $this->storage->store(
                $temporaryPath,
                $storagePath
            );

            $this->storage->cleanupOldDumps(
                $cleanupDirectory,
                config('db-dumper.max_dumps', -1)
            );
        } finally {
            if (
                $temporarySqlPath
                && is_file($temporarySqlPath)
            ) {
                @unlink($temporarySqlPath);
            }

            if (
                $temporaryZipPath
                && is_file($temporaryZipPath)
            ) {
                @unlink($temporaryZipPath);
            }
        }
    }
}
