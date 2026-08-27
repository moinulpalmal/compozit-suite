<?php

namespace Database\Seeders\Admin;

use App\Enums\Admin\DesignationStatus;
use App\Models\Admin\Designation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
                ['short_form' => $shortForm, 'status' => DesignationStatus::Active],
            );
        }
    }
}
