<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Writes a timestamped `mysqldump` of the application database and prunes old
 * ones.
 *
 * The LAN deployment is a single Windows box holding real order data, so this
 * is the only thing standing between a bad migration — or a dead disk — and
 * losing it. It runs nightly from the scheduler and once more from `deploy.ps1`
 * immediately before `migrate --force`.
 *
 * Credentials come from the `mysql` connection rather than a second copy in
 * this file, so rotating the database password does not silently break backups.
 * They are handed to `mysqldump` through a temporary defaults-file, never on
 * the command line: arguments are world-readable in the Windows process list,
 * and `--password=` on the command line would expose the database password to
 * every account on the machine.
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database {--keep-all : Write the dump but skip pruning old ones}';

    protected $description = 'Dump the application database and prune dumps past the retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = (string) config('backup.connection');

        /** @var array<string, mixed>|null $connection */
        $connection = config("database.connections.{$name}");

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->components->error(
                "backup:database only supports MySQL; connection [{$name}] is not a MySQL connection."
            );

            return self::FAILURE;
        }

        $directory = (string) config('backup.path');
        File::ensureDirectoryExists($directory);

        $database = (string) $connection['database'];
        $target = $directory.DIRECTORY_SEPARATOR.sprintf(
            '%s-%s.sql',
            $database,
            Carbon::now()->format('Y-m-d_His'),
        );

        $defaultsFile = $this->writeDefaultsFile($connection);

        try {
            $result = Process::timeout((int) config('backup.timeout'))
                ->run($this->command($defaultsFile, $database, $target));
        } finally {
            /*
             * The defaults file holds the database password in clear text, so
             * it is removed whether the dump succeeded, failed or threw.
             */
            File::delete($defaultsFile);
        }

        if ($result->failed() || $this->reportedAnError($result->errorOutput())) {
            /*
             * Deleted rather than left behind: a truncated `.sql` sitting in the
             * backup directory looks exactly like a good one, and would be the
             * file someone reaches for in an emergency.
             */
            File::delete($target);

            $message = 'Database backup failed: '.trim($result->errorOutput() ?: $result->output());

            Log::error($message);
            $this->components->error($message);

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Database backed up to %s (%s).',
            $target,
            $this->humanSize(File::exists($target) ? (int) File::size($target) : 0),
        ));

        if (! $this->option('keep-all')) {
            $this->prune($directory, $database);
        }

        return self::SUCCESS;
    }

    /**
     * Whether mysqldump reported an error regardless of its exit code.
     *
     * The exit code alone is not enough. Observed on MySQL 9.6: dumping as a
     * user without the global `PROCESS` privilege prints
     * *"mysqldump: Error: 'Access denied…' when trying to dump tablespaces"*
     * and **still exits 0**, having written a file that looks complete. Trusting
     * the exit code would report that backup as a success.
     *
     * Matched on `Error:` specifically, not on any stderr output: mysqldump
     * writes routine notices there too — `[Warning] Using a password on the
     * command line interface can be insecure` appears on every run that does not
     * use an option file — and failing on those would make every backup fail.
     */
    private function reportedAnError(string $errorOutput): bool
    {
        return str_contains($errorOutput, 'Error:');
    }

    /**
     * Build the `mysqldump` invocation.
     *
     * `--defaults-extra-file` has to be the *first* argument — mysqldump reads
     * option files before anything else and ignores it anywhere later.
     * `--single-transaction` takes a consistent snapshot of the InnoDB tables
     * without locking them, so the dump does not block the application, and
     * `--routines`/`--triggers` are included because a schema-only dump that
     * silently drops them is not a restore.
     *
     * The two negative flags are the ones a restore drill found the hard way,
     * and neither is optional:
     *
     * - `--set-gtid-purged=OFF`. The server has GTIDs enabled, so mysqldump
     *   otherwise writes a `SET @@GLOBAL.GTID_PURGED=…` statement near the top
     *   of the file. Restoring that onto the same server fails outright with
     *   *ERROR 3546: GTID_PURGED cannot be changed* — which is the single most
     *   likely restore anyone here will ever attempt. The dumps were unusable
     *   for it until this was added.
     * - `--no-tablespaces`. Reading tablespace metadata needs the global
     *   `PROCESS` privilege, which a MySQL user scoped to this one schema does
     *   not have. Without it the dump fails the moment backups stop running as
     *   root — see documentation/deployment.md §3.
     *
     * @return list<string>
     */
    private function command(string $defaultsFile, string $database, string $target): array
    {
        return [
            (string) config('backup.mysqldump_path'),
            '--defaults-extra-file='.$defaultsFile,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--set-gtid-purged=OFF',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$target,
            $database,
        ];
    }

    /**
     * Write the credentials to a temporary MySQL option file.
     *
     * Returns the path; the caller is responsible for deleting it.
     *
     * @param  array<string, mixed>  $connection
     */
    private function writeDefaultsFile(array $connection): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'compozit-dump-');

        File::put($path, implode(PHP_EOL, [
            '[client]',
            'host='.$connection['host'],
            'port='.$connection['port'],
            'user='.$connection['username'],
            'password="'.$connection['password'].'"',
            '',
        ]));

        return $path;
    }

    /**
     * Delete dumps older than the retention window.
     *
     * Only files this command wrote are considered — the glob is anchored to
     * the database name — so pointing BACKUP_PATH at a shared directory cannot
     * make the command delete somebody else's files.
     */
    private function prune(string $directory, string $database): void
    {
        $cutoff = Carbon::now()->subDays((int) config('backup.retention_days'));
        $deleted = 0;

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.$database.'-*.sql') as $dump) {
            try {
                if (Carbon::createFromTimestamp(File::lastModified($dump))->lessThan($cutoff)) {
                    File::delete($dump);
                    $deleted++;
                }
            } catch (Throwable $exception) {
                Log::warning("Could not prune backup [{$dump}]: ".$exception->getMessage());
            }
        }

        if ($deleted > 0) {
            $this->components->info("Pruned {$deleted} backup(s) older than ".config('backup.retention_days').' days.');
        }
    }

    /**
     * Render a byte count for the console line.
     */
    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
