<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * The suite runs on SQLite (phpunit.xml), so every test here describes a MySQL
 * connection for the command to target — the command reads the live config
 * rather than carrying its own copy of the credentials. `database.default` is
 * deliberately left alone: repointing it breaks `RefreshDatabase`'s transaction
 * for the whole test, which is why `backup.connection` exists as a key of its
 * own.
 */
beforeEach(function () {
    $this->backupPath = storage_path('framework/testing/backups');

    File::deleteDirectory($this->backupPath);

    config()->set('backup.connection', 'mysql');
    config()->set('database.connections.mysql', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'compozitsuite',
        'username' => 'compozit',
        'password' => 'secret-password',
    ]);
    config()->set('backup.path', $this->backupPath);
    config()->set('backup.retention_days', 14);
    config()->set('backup.mysqldump_path', 'C:\\laragon\\bin\\mysql\\bin\\mysqldump.exe');
    config()->set('backup.timeout', 900);
});

afterEach(function () {
    File::deleteDirectory($this->backupPath);
});

test('it dumps the database with a consistent, non-locking snapshot', function () {
    Process::fake();

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(function ($process) {
        $command = $process->command;

        expect($command[0])->toBe('C:\\laragon\\bin\\mysql\\bin\\mysqldump.exe')
            // mysqldump ignores the option file unless it comes first.
            ->and($command[1])->toStartWith('--defaults-extra-file=')
            ->and($command)->toContain('--single-transaction')
            ->and($command)->toContain('--routines')
            ->and($command)->toContain('--triggers')
            ->and(end($command))->toBe('compozitsuite');

        return true;
    });
});

/*
 * Both flags were found by actually restoring a dump, not by reading the manual.
 * Without --set-gtid-purged=OFF the file carries a SET @@GLOBAL.GTID_PURGED line
 * and restoring it onto the same server dies with ERROR 3546; without
 * --no-tablespaces the dump itself fails once it runs as a schema-scoped MySQL
 * user rather than root, because tablespace metadata needs global PROCESS.
 */
test('the dump is restorable onto the same server and runs without global privileges', function () {
    Process::fake();

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(function ($process) {
        expect($process->command)->toContain('--set-gtid-purged=OFF')
            ->and($process->command)->toContain('--no-tablespaces');

        return true;
    });
});

/*
 * Arguments are visible to every account on the machine in the Windows process
 * list, so the database password must never appear in one.
 */
test('it never passes the database password on the command line', function () {
    Process::fake();

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(function ($process) {
        expect(implode(' ', $process->command))->not->toContain('secret-password');

        return true;
    });
});

test('it writes the dump into the configured directory, creating it if missing', function () {
    Process::fake();

    expect(File::isDirectory($this->backupPath))->toBeFalse();

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(function ($process) {
        $resultFile = collect($process->command)
            ->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));

        expect($resultFile)->toStartWith('--result-file='.$this->backupPath)
            ->and($resultFile)->toEndWith('.sql')
            ->and($resultFile)->toContain('compozitsuite-');

        return true;
    });

    expect(File::isDirectory($this->backupPath))->toBeTrue();
});

test('it prunes dumps past the retention window and keeps the rest', function () {
    Process::fake();

    File::ensureDirectoryExists($this->backupPath);

    $stale = $this->backupPath.'/compozitsuite-2020-01-01_000000.sql';
    $fresh = $this->backupPath.'/compozitsuite-2020-02-01_000000.sql';
    $foreign = $this->backupPath.'/someone-elses-backup.sql';

    foreach ([$stale, $fresh, $foreign] as $file) {
        File::put($file, 'dump');
    }

    touch($stale, Carbon::now()->subDays(30)->getTimestamp());
    touch($foreign, Carbon::now()->subDays(30)->getTimestamp());
    touch($fresh, Carbon::now()->subDays(2)->getTimestamp());

    $this->artisan('backup:database')->assertSuccessful();

    expect(File::exists($stale))->toBeFalse()
        ->and(File::exists($fresh))->toBeTrue()
        // Anchored to the database name, so a shared directory stays safe.
        ->and(File::exists($foreign))->toBeTrue();
});

test('--keep-all writes a dump without pruning', function () {
    Process::fake();

    File::ensureDirectoryExists($this->backupPath);

    $stale = $this->backupPath.'/compozitsuite-2020-01-01_000000.sql';
    File::put($stale, 'dump');
    touch($stale, Carbon::now()->subDays(90)->getTimestamp());

    $this->artisan('backup:database', ['--keep-all' => true])->assertSuccessful();

    expect(File::exists($stale))->toBeTrue();
});

/*
 * A truncated .sql looks exactly like a good one to whoever reaches for it in
 * an emergency, and a failed run must not prune the good history behind it.
 */
test('a failed dump reports failure, leaves no partial file and prunes nothing', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Access denied for user', exitCode: 1)]);

    File::ensureDirectoryExists($this->backupPath);

    $stale = $this->backupPath.'/compozitsuite-2020-01-01_000000.sql';
    File::put($stale, 'dump');
    touch($stale, Carbon::now()->subDays(90)->getTimestamp());

    $this->artisan('backup:database')->assertFailed();

    expect(File::exists($stale))->toBeTrue()
        ->and(File::glob($this->backupPath.'/compozitsuite-*.sql'))->toHaveCount(1);
});

/*
 * Observed on MySQL 9.6: mysqldump can print "Error: ..." and still exit 0,
 * leaving a file that looks complete. Trusting the exit code alone would report
 * that as a good backup.
 */
test('a dump that reports an error is a failure even when it exits zero', function () {
    Process::fake(['*' => Process::result(
        output: '',
        errorOutput: "mysqldump: Error: 'Access denied; you need the PROCESS privilege' when trying to dump tablespaces",
        exitCode: 0,
    )]);

    $this->artisan('backup:database')->assertFailed();

    expect(File::glob($this->backupPath.'/compozitsuite-*.sql'))->toBeEmpty();
});

/*
 * ...but mysqldump writes a password notice to stderr on every run that does not
 * use an option file, and treating all stderr as failure would fail every backup.
 */
test('a routine stderr warning is not treated as a failure', function () {
    Process::fake(['*' => Process::result(
        output: '',
        errorOutput: 'mysqldump: [Warning] Using a password on the command line interface can be insecure.',
        exitCode: 0,
    )]);

    $this->artisan('backup:database')->assertSuccessful();
});

test('the temporary credentials file is deleted whether the dump succeeds or fails', function () {
    Process::fake();

    $before = count(File::glob(sys_get_temp_dir().'/compozit-dump-*'));

    $this->artisan('backup:database')->assertSuccessful();

    Process::fake(['*' => Process::result(exitCode: 1)]);
    $this->artisan('backup:database')->assertFailed();

    expect(File::glob(sys_get_temp_dir().'/compozit-dump-*'))->toHaveCount($before);
});

test('it refuses to run against a non-MySQL connection instead of writing an empty dump', function () {
    config()->set('backup.connection', 'sqlite');

    Process::fake();

    $this->artisan('backup:database')->assertFailed();

    Process::assertNothingRan();
});

test('the nightly schedule is registered at the configured time', function () {
    config()->set('backup.time', '01:00');

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'backup:database'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 1 * * *');
});
