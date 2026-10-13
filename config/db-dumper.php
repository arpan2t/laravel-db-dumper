<?php

return [
    'disk' => 'local',  //for s3 => 's3'
    'path' => 'database-dumps',
    'compress' => false,
    'max_dumps' => -1,
    'tenancy' => [
        'enabled' => false,
        'central_connection' => 'central',
        'table' => 'sites',
        'columns' => [
            'id' => 'id',
            'name' => 'name',
            'host' => 'db_host',
            'port' => 'db_port',
            'database' => 'db_database',
            'username' => 'db_username',
            'password' => 'db_password',
            'allow_backup' => 'allow_backup', // column name whose value should be '1' in order to take backup of that tenant
        ]
    ],
];
