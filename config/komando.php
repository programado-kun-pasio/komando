<?php

return [
    'database_sync' => [
        'default_connection' => 'mysql',
        'connections' => ['mysql'],
        
        'ssh' => [
            'host' => env('KOMANDO_SSH_HOST'),
            'user' => env('KOMANDO_SSH_USER', 'app'),
            'port' => env('KOMANDO_SSH_PORT', 22),
        ],
        
        'remote_database' => [
            'host' => env('KOMANDO_REMOTE_DB_HOST', '127.0.0.1'),
            'user' => env('KOMANDO_REMOTE_DB_USER', 'default'),
            'password' => env('KOMANDO_REMOTE_DB_PASSWORD'),
        ],
        
        'commands' => [
            'local' => ['scp', '7z', 'mysql'],
            'remote' => ['mysqldump', '7z'],
        ],
        
        'compression' => [
            'level' => 9,
        ],
        
        'mysqldump' => [
            'options' => ['--skip-lock-tables'],
        ],
        
        'mysql' => [
            'import_timeout' => env('KOMANDO_MYSQL_TIMEOUT', 300), // seconds
        ],
        
        'safety' => [
            'allow_production_wipe' => false,
        ],
    ],
];