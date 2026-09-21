<?php

namespace Arpan\DatabaseDumper;

use Arpan\DatabaseDumper\Compression\BackupCompressor;
use Arpan\DatabaseDumper\Console\Commands\DumpDatabaseCommand;
use Arpan\DatabaseDumper\Database\DatabaseDumper;
use Arpan\DatabaseDumper\Database\Drivers\MySqlDumper;
use Arpan\DatabaseDumper\Security\SecureTemporaryFile;
use Arpan\DatabaseDumper\Storage\BackupStorage;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\ServiceProvider;

class DatabaseDumperServiceProvider extends ServiceProvider
{
    public function register()
    {

        $this->app->singleton(SecureTemporaryFile::class, function () {
            return new SecureTemporaryFile();
        });

        $this->app->singleton(BackupCompressor::class, function () {
            return new BackupCompressor();
        });

        $this->app->singleton(MySqlDumper::class, function ($app) {
            return new MySqlDumper(
                $app->make(SecureTemporaryFile::class)
            );
        });

        $this->app->singleton(DatabaseDumper::class, function ($app) {
            return new DatabaseDumper(
                $app->make(MySqlDumper::class)
            );
        });


        $this->app->singleton(BackupStorage::class, function ($app) {
            $diskName = config('db-dumper.disk', 'local');

            $disk = $app->make(FilesystemManager::class)
                ->disk($diskName);

            return new BackupStorage($disk);
        });
    }

    public function boot()
    {
        $this->commands([
            DumpDatabaseCommand::class,
        ]);

        $this->publishes([
            __DIR__ . '/../config/db-dumper.php'
            => config_path('db-dumper.php'),
        ], 'db-dumper-config');
    }
}
