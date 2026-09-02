<?php

namespace Database\Seeders;

use App\Models\Admin\Buyer;
use App\Models\Admin\Designation;
use App\Models\Admin\Role;
use App\Models\User;
use Database\Seeders\Admin\BuyerSeeder;
use Database\Seeders\Admin\DesignationSeeder;
use Database\Seeders\Admin\RolePermissionSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(DesignationSeeder::class);
        $this->call(BuyerSeeder::class);

        /*
         * `firstWhere` rather than a bare `create`: `employee_id` is unique, so this
         * used to make the second `db:seed` die on a duplicate-key error before any
         * later seeder ran. Re-running is routine — the RBAC catalogue is re-seeded
         * whenever a permission is added — so it has to survive it.
         */
        $admin = User::query()->firstWhere('employee_id', '15868')
            ?? User::factory()->create([
                'name' => 'Test User',
                'employee_id' => '15868',
                'email' => 'test@example.com',
                'approval_authority' => true,
                'designation_id' => Designation::query()->where('name', 'Managing Director')->value('id'),
            ]);

        $admin->assignRole(Role::SUPER_ADMIN);

        $this->seedMerchandisingUsers();
    }

    /**
     * Two accounts either side of the document library's permission boundary.
     *
     * They exist to be compared. `merchandiser` holds the whole `merchandising.`
     * prefix, so that account sees every screen in the module including Documents;
     * `document-uploader` holds `merchandising.documents.*` and nothing else, so that
     * account sees one sidebar link. Logging in as each is the only way to check that
     * a permission-gated surface is actually gated — `useCan()` hides a link, and
     * hiding a link is not authorization (ARCHITECTURE.md §9.1).
     *
     * **Both get real buyer grants rather than `all_buyer_access`.** The wildcard would
     * make every buyer-scoped list pass trivially and hide exactly the bug the scope
     * exists to prevent. The password is the factory's — `password`.
     */
    protected function seedMerchandisingUsers(): void
    {
        $designation = Designation::query()->where('name', 'Merchandiser')->value('id')
            ?? Designation::query()->value('id');

        $buyers = Buyer::query()->orderBy('id')->limit(3)->pluck('id')->all();

        $accounts = [
            ['Merchandising User', '20001', 'merchandiser@example.com', 'merchandiser'],
            ['Document Uploader', '20002', 'documents@example.com', 'document-uploader'],
        ];

        foreach ($accounts as [$name, $employeeId, $email, $role]) {
            /*
             * Idempotent, unlike the super admin above: `employee_id` is unique, so a
             * plain `factory()->create()` makes the second `db:seed` fail outright
             * rather than no-op. Re-running a seeder is routine — the RBAC catalogue
             * is re-seeded every time a permission is added — so anything reached from
             * `DatabaseSeeder` has to survive it.
             */
            $user = User::query()->firstWhere('employee_id', $employeeId)
                ?? User::factory()->create([
                    'name' => $name,
                    'employee_id' => $employeeId,
                    'email' => $email,
                    'designation_id' => $designation,
                ]);

            $user->syncRoles([$role]);
            $user->buyers()->sync($buyers);
        }
    }
}
