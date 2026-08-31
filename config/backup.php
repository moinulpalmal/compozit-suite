<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    |
    | `php artisan backup:database` reads these. The scheduler runs it nightly
    | (routes/console.php) and deploy.ps1 runs it once more immediately before
    | `migrate --force`, so a migration that goes wrong has a dump taken minutes
    | earlier rather than hours.
    |
    | These live in config — and therefore in .env — rather than in a settings
    | table because `settings/application` is still an unbuilt scaffold
    | (ARCHITECTURE.md §5, Module 2). When that surface is built it should write
    | to these same keys; the command reads `config()` and will not need to
    | change.
    |
    */

    /*
     * The connection to dump, as named in config/database.php. Defaults to
     * whatever the application itself uses.
     *
     * This is a key of its own rather than a read of `database.default`
     * because the test suite runs on SQLite: without it, exercising the command
     * would mean repointing the default connection, which breaks
     * `RefreshDatabase`'s transaction.
     */
    'connection' => env('BACKUP_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /*
     * Where dumps are written. Created if missing. Point this at a different
     * physical drive from the database — a dump beside the data it protects
     * survives a bad migration but not a failed disk.
     */
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
     * Dumps older than this are deleted after each successful run. Nothing
     * prunes on a failed run, so a broken backup never eats the good history
     * behind it.
     */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    /*
     * `mysqldump` is not on the PATH under Laragon. Set the absolute path to
     * the binary belonging to the MySQL version actually serving the database —
     * e.g. D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe. A dump
     * taken by an older client against a newer server can be silently lossy.
     */
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    /*
     * When the scheduled nightly dump runs, in the application's timezone.
     */
    'time' => env('BACKUP_TIME', '01:00'),

    /*
     * How long a single dump may take before it is abandoned, in seconds.
     */
    'timeout' => (int) env('BACKUP_TIMEOUT', 900),

];
