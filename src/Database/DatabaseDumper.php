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

    public function dump(array $config, $outputPath)
    {
        return $this->mysqlDumper->dump(
            $config,
            $outputPath
        );
    }
}