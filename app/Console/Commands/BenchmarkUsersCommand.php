<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Measures the Admin user list's queries against a realistically sized table,
 * with and without each candidate index.
 *
 * This exists so index decisions are measured rather than argued. Run it, read
 * the milliseconds, and add only the indexes that actually moved a number —
 * every index costs write throughput forever, so one that changes nothing is a
 * permanent tax. See documentation/admin.md §2.1.
 */
class BenchmarkUsersCommand extends Command
{
    protected $signature = 'users:benchmark {--rows=5000 : How many throwaway users to seed}';

    protected $description = 'Benchmark the Admin user list queries against candidate indexes';

    /**
     * Indexes to test, as name => columns.
     *
     * `(deleted_at, name)` is absent because it already exists in a migration.
     *
     * @var array<string, list<string>>
     */
    protected const array CANDIDATES = [
        'bench_deleted_employee_id' => ['deleted_at', 'employee_id'],
        'bench_deleted_email' => ['deleted_at', 'email'],
        'bench_deleted_created_at' => ['deleted_at', 'created_at'],
        'bench_deleted_personal_mobile' => ['deleted_at', 'personal_mobile_no'],
        'bench_deleted_official_mobile' => ['deleted_at', 'official_mobile_no'],
        'bench_deleted_extension' => ['deleted_at', 'official_extension_no'],
        'bench_deleted_approved_name' => ['deleted_at', 'approved', 'name'],
        'bench_deleted_gender_name' => ['deleted_at', 'gender', 'name'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! App::environment('local')) {
            $this->error('This command seeds and deletes rows in the live database. It only runs in the local environment.');

            return self::FAILURE;
        }

        $rows = max(100, (int) $this->option('rows'));
        $seeded = [];

        try {
            $seeded = $this->seed($rows);

            $this->measure('Baseline — only the shipped indexes', null);

            foreach (self::CANDIDATES as $name => $columns) {
                $this->measure(
                    sprintf('With %s (%s)', $name, implode(', ', $columns)),
                    [$name, $columns],
                );
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            // MySQL commits implicitly on DDL, so cleanup cannot ride on a
            // transaction — it has to be explicit, and has to run even on error.
            $this->cleanup($seeded);
        }

        $this->newLine();
        $this->info('Add an index only where the "after" column is meaningfully lower than "before".');

        return self::SUCCESS;
    }

    /**
     * Seed throwaway users and return their ids.
     *
     * @return list<int>
     */
    protected function seed(int $rows): array
    {
        $this->info("Seeding {$rows} throwaway users…");

        $before = User::withTrashed()->pluck('id')->all();

        // Chunked so 5k rows do not build one enormous insert.
        foreach (array_chunk(range(1, $rows), 500) as $chunk) {
            User::factory()->count(count($chunk))->create();
        }

        $after = User::withTrashed()->pluck('id')->all();
        $seeded = array_values(array_diff($after, $before));

        // A tenth are soft-deleted so the historical tab has rows to scan, and a
        // twentieth are inactive so the status filter has a genuinely selective
        // case — that is the one where a sort index has to scan a long way to
        // fill a page of 25.
        $trashed = array_slice($seeded, 0, (int) ($rows / 10));
        $inactive = array_slice($seeded, (int) ($rows / 10), (int) ($rows / 20));

        User::query()->whereIn('id', $trashed)->delete();
        User::query()->whereIn('id', $inactive)->update(['approved' => false]);

        return $seeded;
    }

    /**
     * Run every query pattern, optionally with one candidate index present.
     *
     * @param  array{0: string, 1: list<string>}|null  $index
     */
    protected function measure(string $label, ?array $index): void
    {
        if ($index !== null) {
            Schema::table('users', fn ($table) => $table->index($index[1], $index[0]));
        }

        $this->newLine();
        $this->line("<comment>{$label}</comment>");

        $results = [];

        foreach ($this->patterns() as $name => $sql) {
            $results[] = [$name, $this->time($sql), $this->indexUsedBy($sql)];
        }

        $this->table(['Query', 'ms', 'Index chosen'], $results);

        if ($index !== null) {
            Schema::table('users', fn ($table) => $table->dropIndex($index[0]));
        }
    }

    /**
     * The queries the Admin user list actually issues.
     *
     * Search prefixes are sampled from real seeded rows and split into a
     * selective case (what someone types to find one person) and a broad one
     * (a couple of characters that match a large slice of the table). The
     * distinction matters: a prefix matching a seventh of the table is one
     * MySQL will rightly ignore an index for, so benchmarking only the broad
     * case would reject a perfectly good index.
     *
     * @return array<string, string>
     */
    protected function patterns(): array
    {
        $base = 'SELECT * FROM users WHERE deleted_at IS NULL';
        $sample = $this->sample();

        return [
            'default (name asc)' => "{$base} ORDER BY name ASC LIMIT 25",
            'sort employee_id' => "{$base} ORDER BY employee_id ASC LIMIT 25",
            'sort email desc' => "{$base} ORDER BY email DESC LIMIT 25",
            'sort created_at desc' => "{$base} ORDER BY created_at DESC LIMIT 25",
            'search name (broad)' => "{$base} AND name LIKE 'Ma%' ORDER BY name LIMIT 25",
            'search employee_id (selective)' => "{$base} AND employee_id LIKE '{$sample['employee_id']}%' ORDER BY name LIMIT 25",
            'search mobile (selective)' => "{$base} AND personal_mobile_no LIKE '{$sample['personal_mobile_no']}%' ORDER BY name LIMIT 25",
            'search mobile (broad)' => "{$base} AND personal_mobile_no LIKE '017%' ORDER BY name LIMIT 25",
            'search extension (selective)' => "{$base} AND official_extension_no LIKE '{$sample['official_extension_no']}%' ORDER BY name LIMIT 25",
            'filter gender' => "{$base} AND gender = 'F' ORDER BY name LIMIT 25",
            'filter inactive' => "{$base} AND approved = 0 ORDER BY name LIMIT 25",
            'historical tab' => 'SELECT * FROM users WHERE deleted_at IS NOT NULL ORDER BY name LIMIT 25',
            'deep page (offset 2000)' => "{$base} ORDER BY name ASC LIMIT 25 OFFSET 2000",
        ];
    }

    /**
     * Selective search prefixes taken from a real row.
     *
     * @return array{employee_id: string, personal_mobile_no: string, official_extension_no: string}
     */
    protected function sample(): array
    {
        $user = User::query()->whereNotNull('personal_mobile_no')->firstOrFail();

        return [
            // Long enough to identify one person, as a real lookup would be.
            'employee_id' => substr((string) $user->employee_id, 0, 4),
            'personal_mobile_no' => substr((string) $user->personal_mobile_no, 0, 8),
            'official_extension_no' => (string) $user->official_extension_no,
        ];
    }

    /**
     * Median milliseconds over a few runs, to damp cache warm-up noise.
     */
    protected function time(string $sql): string
    {
        $samples = [];

        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            DB::select($sql);
            $samples[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($samples);

        return number_format($samples[2], 2);
    }

    /**
     * The index name MySQL's plan mentions, if any.
     */
    protected function indexUsedBy(string $sql): string
    {
        $plan = DB::select('EXPLAIN '.$sql)[0]->EXPLAIN ?? '';

        if (preg_match('/using (\w+)/i', $plan, $matches) === 1) {
            return $matches[1].(str_contains($plan, 'Sort:') ? ' + filesort' : '');
        }

        return str_contains($plan, 'Table scan') ? 'table scan' : '—';
    }

    /**
     * Remove the seeded rows and any leftover candidate index.
     *
     * @param  list<int>  $seeded
     */
    protected function cleanup(array $seeded): void
    {
        foreach (array_keys(self::CANDIDATES) as $name) {
            if (Schema::hasIndex('users', $name)) {
                Schema::table('users', fn ($table) => $table->dropIndex($name));
            }
        }

        if ($seeded !== []) {
            User::withTrashed()->whereIn('id', $seeded)->forceDelete();
            $this->info(count($seeded).' seeded users removed.');
        }
    }
}
