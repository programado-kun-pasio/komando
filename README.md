# Komando Database Sync

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

Ensure that all required dependencies are installed on both the local and remote systems.

## Usage

```bash
php artisan komando:sync:database [db-connections] [ssh-user] [ssh-host]
```

### Parameters

- `db-connections` (Optional): The local database connection names, comma-separated. Default: `mysql`
- `ssh-user` (Optional): The SSH user of the source system. Default: `app`
- `ssh-host` (Required): The SSH host of the source system

### Examples

```bash
# Synchronize the default MySQL database
php artisan komando:sync:database mysql app example.com

# Synchronize multiple databases
php artisan komando:sync:database mysql,pgsql app example.com

# Use a custom SSH user
php artisan komando:sync:database mysql user example.com
```

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
