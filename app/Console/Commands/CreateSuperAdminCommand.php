<?php

namespace App\Console\Commands;

use App\Concerns\EmployeeValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Enums\Admin\Gender;
use App\Enums\RecordStatus;
use App\Models\Admin\Designation;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the one account that can log into a freshly deployed database.
 *
 * A production deploy runs `RolePermissionSeeder` and `DesignationSeeder` and
 * nothing else — `DatabaseSeeder` is never invoked there, because it hardcodes
 * `test@example.com` / `password`. That leaves `users` empty, and since the
 * login identifier is `employee_id` rather than email (ARCHITECTURE.md §9.6)
 * there is no way in at all. This is that way in.
 *
 * **Idempotent**: `deploy.ps1` calls it on every deploy. A second run finds the
 * existing user, re-asserts the super-admin role, and leaves the password and
 * every other attribute alone — an admin who has since changed their password
 * or details through the UI does not have them reverted by a deploy.
 */
class CreateSuperAdminCommand extends Command
{
    use EmployeeValidationRules, PasswordValidationRules;

    protected $signature = 'admin:create-super';

    protected $description = 'Create or repair the bootstrap super-administrator from config/admin.php';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var array{employee_id: string|null, name: string|null, email: string|null, password: string|null, designation: string|null} $config */
        $config = config('admin.super');

        if (($failure = $this->validate($config)) !== null) {
            $this->components->error($failure);
            $this->line('  Set ADMIN_EMPLOYEE_ID, ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in .env.');

            return self::FAILURE;
        }

        $user = User::query()->withTrashed()->firstWhere('employee_id', $config['employee_id']);

        if ($user instanceof User) {
            $this->promote($user);

            $this->components->info("Super administrator [{$user->employee_id}] already exists; role re-asserted, password unchanged.");

            return self::SUCCESS;
        }

        $this->promote($this->create($config));

        $this->components->info("Super administrator [{$config['employee_id']}] created.");

        return self::SUCCESS;
    }

    /**
     * Check the configured credentials against the application's own rules.
     *
     * The password is held to `Password::default()`, the same policy the
     * registration and reset forms enforce, so a deploy cannot install a weaker
     * password than a user could choose for themselves. Uniqueness is *not*
     * checked: an existing employee ID is the idempotent path, not an error.
     *
     * @param  array<string, string|null>  $config
     * @return string|null The first failure, or null when the config is usable.
     */
    private function validate(array $config): ?string
    {
        $validator = Validator::make($config, [
            'employee_id' => ['required', 'string', 'regex:'.self::EMPLOYEE_ID_PATTERN],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->unconfirmedPasswordRules(),
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    /**
     * The shared password rules minus `confirmed`.
     *
     * There is no second field to confirm against on the command line. The
     * filter compares loosely rather than with `array_diff`, which would try to
     * cast the `Password` rule object to a string and fatal.
     *
     * @return array<int, mixed>
     */
    private function unconfirmedPasswordRules(): array
    {
        return array_values(array_filter(
            $this->passwordRules(),
            fn (mixed $rule): bool => $rule !== 'confirmed',
        ));
    }

    /**
     * Create the account.
     *
     * `designation_id` stays null unless ADMIN_DESIGNATION names a real
     * designation, because the column is nullable and the admin can set it from
     * the UI on first login.
     *
     * `email_verified_at` is set after the fact rather than in the array: it is
     * absent from the model's `#[Fillable]` list, so mass assignment would drop
     * it silently.
     *
     * @param  array<string, string|null>  $config
     */
    private function create(array $config): User
    {
        $user = User::query()->create([
            'name' => $config['name'],
            'employee_id' => $config['employee_id'],
            'email' => $config['email'],
            'password' => Hash::make((string) $config['password']),
            'gender' => Gender::Male,
            'status' => RecordStatus::Active,
            'approval_authority' => true,
            'designation_id' => $config['designation'] === null
                ? null
                : Designation::query()->where('name', $config['designation'])->value('id'),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Give the user the super-admin role, creating the role if the RBAC seeder
     * has not run yet.
     *
     * The permission cache is flushed afterwards because the role assignment is
     * invisible to an already-warm registrar, and in production the very next
     * request may be served from the same cache store.
     */
    private function promote(User $user): void
    {
        $user->assignRole(Role::findOrCreate(Role::SUPER_ADMIN, 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
