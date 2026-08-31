<?php

use App\Enums\RecordStatus;
use App\Models\Admin\Designation;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Point config/admin.php at a usable set of credentials.
 *
 * @param  array<string, string|null>  $overrides
 */
function configureSuperAdmin(array $overrides = []): void
{
    config()->set('admin.super', array_merge([
        'employee_id' => '00001',
        'name' => 'Deploy Admin',
        'email' => 'admin@example.test',
        'password' => 'correct-horse-battery-staple',
        'designation' => null,
    ], $overrides));
}

test('it creates an active, verified super administrator', function () {
    configureSuperAdmin();

    $this->artisan('admin:create-super')->assertSuccessful();

    $user = User::query()->firstWhere('employee_id', '00001');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Deploy Admin')
        ->and($user->email)->toBe('admin@example.test')
        ->and($user->status)->toBe(RecordStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(Role::SUPER_ADMIN))->toBeTrue()
        ->and(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue();
});

test('it assigns the designation named by ADMIN_DESIGNATION', function () {
    $designation = Designation::factory()->create(['name' => 'Managing Director']);

    configureSuperAdmin(['designation' => 'Managing Director']);

    $this->artisan('admin:create-super')->assertSuccessful();

    expect(User::query()->firstWhere('employee_id', '00001')->designation_id)
        ->toBe($designation->id);
});

/*
 * deploy.ps1 runs this on every deploy, so a second run must be a no-op rather
 * than a duplicate-key crash — and must not undo a password the admin has since
 * changed through the UI.
 */
test('a second run is a no-op and does not reset the password', function () {
    configureSuperAdmin();

    $this->artisan('admin:create-super')->assertSuccessful();

    $user = User::query()->firstWhere('employee_id', '00001');
    $user->forceFill(['password' => Hash::make('changed-by-the-admin-later')])->save();

    $this->artisan('admin:create-super')->assertSuccessful();

    expect(User::query()->where('employee_id', '00001')->count())->toBe(1)
        ->and(Hash::check('changed-by-the-admin-later', $user->fresh()->password))->toBeTrue();
});

test('it re-asserts the super-admin role on a user who lost it', function () {
    configureSuperAdmin();

    $this->artisan('admin:create-super')->assertSuccessful();

    $user = User::query()->firstWhere('employee_id', '00001');
    $user->removeRole(Role::SUPER_ADMIN);

    $this->artisan('admin:create-super')->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

test('it refuses to run rather than invent a password', function () {
    configureSuperAdmin(['password' => null]);

    $this->artisan('admin:create-super')->assertFailed();

    expect(User::query()->where('employee_id', '00001')->exists())->toBeFalse();
});

test('it refuses a password weaker than the application enforces', function () {
    configureSuperAdmin(['password' => 'abc']);

    $this->artisan('admin:create-super')->assertFailed();

    expect(User::query()->where('employee_id', '00001')->exists())->toBeFalse();
});

test('it refuses a malformed employee id', function () {
    configureSuperAdmin(['employee_id' => 'no spaces allowed here']);

    $this->artisan('admin:create-super')->assertFailed();

    expect(User::query()->count())->toBe(0);
});
