<?php declare(strict_types=1);

return [
    'database_sync' => [
        'default_connection' => 'mysql',
        'connections' => ['mysql'],
        
        'ssh' => [
            'host' => env('KOMANDO_SSH_HOST'),
            'user' => env('KOMANDO_SSH_USER', 'app'),
            'port' => env('KOMANDO_SSH_PORT', 22),
            'password' => env('KOMANDO_SSH_PASSWORD'),
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
            'timeouts' => [
                'import' => env('KOMANDO_MYSQL_TIMEOUT_IMPORT', 300), // seconds
                'copy' => env('KOMANDO_MYSQL_TIMEOUT_COPY', 300), // seconds
            ]
        ],
        
        'safety' => [
            'allow_production_wipe' => false,
        ],

        'after_sync_commands' => [

        ]
    ],
];