<?php declare(strict_types=1);
use Programado\Komando\Files\Models\File;
use Programado\Komando\Files\Models\FileAttachment;
use Programado\Komando\Files\Services\DefaultStoredFileFactory;

return [
    'files' => [
        'enabled' => false,
        'migrations' => true,
        'migrate_file_table' => true,
        'file_model' => File::class,
        'attachment_model' => FileAttachment::class,
        'factory' => DefaultStoredFileFactory::class,
        'disk' => 'files',
        'attachment_table' => 'file_attachments',
        'graphql_slot_type' => 'String',
        'download' => [
            'enabled' => true,
            'path' => 'api/files/{file}/download',
            'middleware' => [],
        ],
    ],

    'database_sync' => [
        'default_connection' => 'mysql',
        'connections' => [env('KOMANDO_CONNECTION', 'mysql')],

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
            'local' => ['scp', '7z'],
            'remote' => ['7z'],
        ],

        'compression' => [
            'level' => 9,
        ],

        'mysqldump' => [
            'options' => ['--skip-lock-tables'],
        ],

        'pg_dump' => [
            'options' => ['--clean', '--if-exists', '--no-owner', '--no-privileges'],
        ],

        'mysql' => [
            'timeouts' => [
                'import' => env('KOMANDO_MYSQL_TIMEOUT_IMPORT', 300), // seconds
                'copy' => env('KOMANDO_MYSQL_TIMEOUT_COPY', 300), // seconds
            ],
        ],

        'safety' => [
            'allow_production_wipe' => false,
        ],

        'after_sync_commands' => [

        ],
    ],
];
