<?php

namespace Database\Seeders\Admin;

use App\Enums\RecordStatus;
use App\Models\Admin\Designation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Seeds the job titles the Admin user form offers.
 *
 * **These are placeholders.** They are a plausible garments-manufacturing set,
 * not the owner's HR list — replace the array with the real titles. The seeder
 * is idempotent (`firstOrCreate` on the unique name), so it is safe to re-run
 * after editing it, and it will not overwrite a title an admin has since
 * renamed through the UI.
 */
class DesignationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Extra throwaway rows seeded in local development only.
     *
     * The real list is 17 titles against a page size of 25, which means the
     * paging controls never appear while building the list screen. These give
     * them something to page through; they are factory noise, clearly not real
     * job titles, and they never reach another environment.
     */
    protected const int LOCAL_FILLER = 40;

    /**
     * The designations the application ships with, as `name => short_form`.
     *
     * @var array<string, string>
     */
    protected const array DESIGNATIONS = [
        'Managing Director' => 'MD',
        'General Manager' => 'GM',
        'Merchandising Manager' => 'MM',
        'Senior Merchandiser' => 'SMER',
        'Merchandiser' => 'MER',
        'Assistant Merchandiser' => 'AMER',
        'Production Manager' => 'PM',
        'Production Officer' => 'PO',
        'Line Supervisor' => 'LSUP',
        'Quality Manager' => 'QM',
        'Quality Inspector' => 'QI',
        'Cutting In-charge' => 'CIC',
        'Finishing In-charge' => 'FIC',
        'Store Officer' => 'STO',
        'IT Officer' => 'ITO',
        'HR Officer' => 'HRO',
        'Accounts Officer' => 'ACO',
    ];

    /**
     * Seed the designations.
     */
    public function run(): void
    {
        foreach (self::DESIGNATIONS as $name => $shortForm) {
            Designation::firstOrCreate(
                ['name' => $name],
                ['short_form' => $shortForm, 'status' => RecordStatus::Active],
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
        $target = count(self::DESIGNATIONS) + self::LOCAL_FILLER;
        $existing = Designation::query()->count();

        if (! App::environment('local') || $existing >= $target) {
            return;
        }

        Designation::factory()->count($target - $existing)->create();
    }
}
