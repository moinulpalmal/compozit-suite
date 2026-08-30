<?php

namespace Database\Seeders\Admin;

use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Seeds the buyers the Admin screens and the access picker offer.
 *
 * **These are placeholders.** They are a plausible set of names for a garments
 * factory's customers, not the owner's buyer list — replace the array with the
 * real one. The seeder is idempotent (`firstOrCreate` on the unique name), so it
 * is safe to re-run after editing, and it will not overwrite a buyer an admin
 * has since renamed through the UI.
 *
 * Mirrors `DesignationSeeder`, including its local-only filler.
 */
class BuyerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Extra throwaway rows seeded in local development only.
     *
     * The real list is well under one page, which means the paging controls
     * never appear while building the list screen. These give them something to
     * page through and never reach another environment.
     */
    protected const int LOCAL_FILLER = 40;

    /**
     * The buyers the application ships with, as `name => code`.
     *
     * @var array<string, string>
     */
    protected const array BUYERS = [
        'H&M' => 'HM',
        'Zara' => 'ZARA',
        'Primark' => 'PRMK',
        'C&A' => 'CA',
        'Next' => 'NEXT',
        'Tesco' => 'TESC',
        'Lidl' => 'LIDL',
        'Kiabi' => 'KIAB',
        'Decathlon' => 'DECA',
        'Walmart' => 'WMT',
    ];

    /**
     * Seed the buyers.
     */
    public function run(): void
    {
        foreach (self::BUYERS as $name => $code) {
            Buyer::firstOrCreate(
                ['name' => $name],
                ['code' => $code, 'status' => RecordStatus::Active],
            );
        }

        $this->fillLocally();
    }

    /**
     * Top the table up past one page, in local development only.
     *
     * Guarded on both the environment and the current count so re-running the
     * seeder does not keep adding rows.
     */
    protected function fillLocally(): void
    {
        $target = count(self::BUYERS) + self::LOCAL_FILLER;
        $existing = Buyer::query()->count();

        if (! App::environment('local') || $existing >= $target) {
            return;
        }

        Buyer::factory()->count($target - $existing)->create();
    }
}
