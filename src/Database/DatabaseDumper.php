<?php

namespace Arpan\DatabaseDumper\Database;

use Arpan\DatabaseDumper\Database\Drivers\MySqlDumper;

class DatabaseDumper
{
    protected $mysqlDumper;

    public function __construct(MySqlDumper $mysqlDumper)
    {
        $this->mysqlDumper = $mysqlDumper;
    }

    public function dump($database, $config, $outputPath)
    {
        if ($database === "mysql") {
            return $this->mysqlDumper->dump(
                $config,
                $outputPath
            );
        }
    }

    public function getExtension($database)
    {
        if ($database === "mysql") return 'sql';
    }
}
