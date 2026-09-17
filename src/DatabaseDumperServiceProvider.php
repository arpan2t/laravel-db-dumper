<?php

namespace Arpan\DatabaseDumper;

use Arpan\DatabaseDumper\Console\Commands\DumpDatabaseCommand;
use Arpan\DatabaseDumper\Database\DatabaseDumper;
use Arpan\DatabaseDumper\Database\Drivers\MySqlDumper;
use Illuminate\Support\ServiceProvider;

class DatabaseDumperServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MySqlDumper::class, function () {
            return new MySqlDumper();
        });

        $this->app->singleton(DatabaseDumper::class, function ($app) {
            return new DatabaseDumper(
                $app->make(MySqlDumper::class)
            );
        });
    }

    public function boot()
    {
        if($this->app->runningInConsole()){
            $this->commands([
                DumpDatabaseCommand::class,
            ]);
        }
        
        $this->publishes([
            __DIR__ . '/../config/db-dumper.php'
                => config_path('db-dumper.php'),
        ], 'db-dumper-config');
    }
}