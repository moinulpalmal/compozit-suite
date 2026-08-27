<?php

namespace Database\Seeders;

use App\Models\Admin\Role;
use App\Models\User;
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

        User::factory()->create([
            'name' => 'Test User',
            'employee_id' => '15868',
            'email' => 'test@example.com',
            'approval_authority' => true,
        ])->assignRole(Role::SUPER_ADMIN);
    }
}
