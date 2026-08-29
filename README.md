# Komando

## GraphQL file attachments

Komando provides reusable named file slots and unassigned file attachments for Eloquent models and Lighthouse mutations.
The package owns the attachment mechanics while each application keeps its own file model, slot enum, authorization,
download route and data migration.

### Requirements

- Configure Lighthouse with `transactional_mutations` enabled so the parent mutation and its files share one transaction.
- The configured file model must contain `name`, `mime_type`, `extension`, `size` and `metadata` columns.
- Enable the file module and configure its file model before running migrations.

The package migration creates `file_attachments`. Laravel derives `file_id` as an integer, UUID or ULID through the
configured file model. `attachable_id` follows Laravel's global morph key type.

```php
Schema::morphUsingUuids();
// Or Schema::morphUsingUlids() when attachment owners use ULIDs.
```

Existing applications keep project-specific data migrations, for example renaming an old polymorphic table or mapping
legacy tags to slots. Set `migrations` to `false` only when the application deliberately owns the attachment table.

### Configuration

Publish the configuration and configure the application's file model:

```bash
php artisan vendor:publish --provider="Programado\Komando\Providers\KomandoServiceProvider" --tag="config"
```

```php
'files' => [
    'enabled' => true,
    'migrations' => true,
    'file_model' => App\Models\File::class,
    'attachment_model' => Programado\Komando\Files\Models\FileAttachment::class,
    'factory' => Programado\Komando\Files\Services\DefaultStoredFileFactory::class,
    'disk' => 'files',
    'attachment_table' => 'file_attachments',
    'graphql_slot_type' => 'FileSlot',
],
```

The file model implements `StoredFileContract` and uses `IsStoredFile`:

```php
use Programado\Komando\Files\Contracts\StoredFileContract;
use Programado\Komando\Files\Traits\IsStoredFile;

class File extends Model implements StoredFileContract
{
    use HasUlids, IsStoredFile;
}
```

Models that own files implement `HasFileAttachmentsContract`:

```php
use Programado\Komando\Files\Contracts\HasFileAttachmentsContract;
use Programado\Komando\Files\Traits\HasFileAttachments;

class Workspace extends Model implements HasFileAttachmentsContract
{
    use HasFileAttachments;
}
```

The application owns and registers its concrete backed `FileSlot` enum. Alternatively, keep the default
`graphql_slot_type` value `String` and quote slot names in the schema.

Publish the shared attachment input:

```bash
php artisan vendor:publish --provider="Programado\Komando\Providers\KomandoServiceProvider" --tag="komando-files-graphql"
```

The directives are discovered automatically and work on output and input fields:

```graphql
type Workspace {
  logo_light: File @fileSlot(slot: WORKSPACE_LOGO_LIGHT)
  files: [File!]! @fileAttachments
}

input UpsertWorkspaceInput {
  id: ID
  logo_light: Upload @fileSlot(slot: WORKSPACE_LOGO_LIGHT)
  files: FileAttachmentChangesInput @fileAttachments
}
```

For `@fileSlot`, omitted input keeps the slot unchanged, an `Upload` replaces it and `null` removes it.
`@fileAttachments` accepts `add: [Upload!]` and `remove: [ID!]`; removal is restricted to unassigned files related to
the mutated owner. New physical files are deleted after rollback, while replaced files are only deleted after commit
and only when no attachment references remain.

Applications with additional required file columns can configure a custom implementation of
`StoredFileFactoryContract`.

## Database sync

## Description

This Artisan command enables the synchronization of databases between a remote system and your local development environment. It creates a dump of the remote database, compresses it, transfers it to your local system, and imports it into your local database.

## Requirements

### Local Requirements
- `scp` - for secure file transfer
- `7z` - for compressing/decompressing database dumps
- `mysql` - for importing the database

### Remote Requirements
- `mysqldump` - for creating database dumps
- `7z` - for compressing database dumps

## Installation

1. Add the repository to your `composer.json`:

```json
"repositories": {
    "komando": {
        "type": "vcs",
        "url": "https://github.com/programado-kun-pasio/komando.git"
    }
}
```

2. Install the package via Composer:

```bash
composer require programado/komando
```

3. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Programado\Komando\Providers\KomandoServiceProvider" --tag="config"
```

4. Configure your environment variables in `.env`:

```env
KOMANDO_SSH_HOST=your-remote-host.com
KOMANDO_SSH_USER=app
KOMANDO_SSH_PORT=22
KOMANDO_REMOTE_DB_HOST=127.0.0.1
KOMANDO_REMOTE_DB_USER=default
KOMANDO_REMOTE_DB_PASSWORD=your-password
```

## Configuration

The package uses a configuration file `config/komando.php` with the following structure:

```php
return [
    'database_sync' => [
        'default_connection' => 'mysql',
        'connections' => ['mysql'], // Database connections to sync
        
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
            'level' => 9, // 7z compression level (1-9)
        ],
        
        'mysqldump' => [
            'options' => ['--skip-lock-tables'],
        ],
        
        'safety' => [
            'allow_production_wipe' => false,
        ],
    ],
];
```

## Usage

```bash
php artisan komando:sync:database
```

The command now reads all configuration from the config file and environment variables. No command-line parameters are needed.

### Configuration Options

- `connections`: Array of database connection names to sync
- `ssh.host`: SSH hostname (required)
- `ssh.user`: SSH username (default: 'app')
- `ssh.port`: SSH port (default: 22)
- `remote_database.*`: Remote database connection settings
- `commands.*`: Required commands for local and remote systems
- `compression.level`: 7z compression level (1-9)
- `mysqldump.options`: Additional mysqldump options
- `safety.allow_production_wipe`: Allow database wipe in production (default: false)

## Process

The command performs the following actions for each specified database connection:

1. Checks if all required commands are available on both local and remote systems
2. Creates a dump of the remote database
3. Compresses the dump on the remote system
4. Transfers the compressed dump to your local system
5. Extracts the dump locally
6. Wipes your local database (with safeguards for production environments)
7. Imports the dump into your local database
8. Runs migrations

## Security Notes

- This command wipes your local database before import! In production environments, additional confirmation is requested.
- Ensure your SSH credentials are secure.
- Avoid using production systems as the target destination.

## Troubleshooting

If the command fails, check the following points:

1. Are all required commands available on both local and remote systems?
2. Do you have access to the remote server via SSH?
3. Does the SSH user have sufficient permissions for database access?
4. Are the database connection settings in your `.env` file correctly configured?
