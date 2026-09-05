<?php

use App\Enums\Admin\AuditEvent;
use App\Models\Admin\AuditLog;
use App\Models\Admin\Buyer;
use App\Models\Admin\Designation;
use App\Models\Admin\Role;
use App\Models\Merchandising\BqsRow;
use App\Models\User;
use App\Services\Admin\BuyerAccessService;
use App\Services\Admin\UserService;
use Database\Seeders\Admin\RolePermissionSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| The audit trail
|--------------------------------------------------------------------------
|
| ARCHITECTURE.md §9.3. `owen-it/laravel-auditing` writes the four model events
| through `App\Concerns\Audited`; `Admin\AuditRecorder` writes everything
| Eloquent cannot see.
|
| **A test that exercises *writing* calls `auditing()` first.** The suite runs in
| console and `config('audit.console')` is `false`, so seeders and artisan
| commands leave no trail — which also means auditing is off for the whole suite
| unless a test turns it on. The flag is read per write rather than at model boot,
| so setting it inside the test is enough.
|
| **The read tests deliberately do not call it**, and that is not an oversight.
| With auditing on, the fixtures write audits of their own — `userWithPermissions()`
| creates a user, a permission and a role, and `AuditLog::factory()` creates the
| `Designation` it points at — so a list assertion counting rows would be counting
| its own setup. Reading is proved against rows the factory made; writing is proved
| separately.
|
*/

/**
 * Record audits for the rest of this test.
 *
 * Call it *after* any fixture whose creation should not appear in the trail.
 */
function auditing(): void
{
    config(['audit.console' => true]);
}

/*
|--------------------------------------------------------------------------
| The mechanism
|--------------------------------------------------------------------------
*/

test('creating an audited model records the new values and the actor', function () {
    $actor = userWithPermissions('admin.designations.create');

    $this->actingAs($actor);

    auditing();

    $designation = Designation::factory()->create(['name' => 'Merchandiser']);

    $audit = AuditLog::query()->latest('id')->firstOrFail();

    expect($audit->event)->toBe(AuditEvent::Created->value)
        ->and($audit->auditable_type)->toBe('designation')
        ->and($audit->auditable_id)->toBe($designation->id)
        ->and($audit->new_values['name'])->toBe('Merchandiser')
        ->and($audit->old_values)->toBe([])
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->actor_name)->toBe($actor->name)
        ->and($audit->actor_employee_id)->toBe($actor->employee_id);
});

test('updating an audited model records only the columns that changed', function () {
    $actor = userWithPermissions('admin.designations.update');

    $this->actingAs($actor);

    auditing();

    $designation = Designation::factory()->create(['name' => 'Merchandiser']);

    $designation->update(['name' => 'Senior Merchandiser']);

    $audit = AuditLog::query()->where('event', AuditEvent::Updated->value)->latest('id')->firstOrFail();

    // A diff, not a snapshot — `short_form` and `status` did not move and are absent.
    expect($audit->old_values['name'])->toBe('Merchandiser')
        ->and($audit->new_values['name'])->toBe('Senior Merchandiser')
        ->and($audit->old_values)->not->toHaveKey('short_form')
        ->and($audit->new_values)->not->toHaveKey('status');

    /*
     * `last_updated_by` moves in the same save, because `ActorObserver` stamps it
     * (§9.3), so it is legitimately part of the diff. It is deliberately *not*
     * excluded: the trail should say what the row now holds. The list resolves
     * the id to a name rather than showing a bare number — see
     * `AuditLogService::describeValues()`.
     */
    expect($audit->old_values['last_updated_by'])->toBeNull()
        ->and($audit->new_values['last_updated_by'])->toBe($actor->id);
});

test('the auditable type is a morph alias, not a class name', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    auditing();

    Designation::factory()->create();

    // The whole reason the morph map exists: a class name here would orphan this
    // row the first time the model moved. See AppServiceProvider::MORPH_MAP.
    expect(AuditLog::query()->latest('id')->firstOrFail()->auditable_type)
        ->toBe('designation')
        ->not->toContain('\\');
});

/*
|--------------------------------------------------------------------------
| Redaction
|--------------------------------------------------------------------------
*/

test('a credential never reaches the audit trail', function () {
    $this->actingAs(userWithPermissions('admin.users.update'));

    $user = User::factory()->create();

    auditing();

    $user->update([
        'password' => 'a-brand-new-secret-Value1!',
        'name' => 'Renamed',
    ]);

    $audit = AuditLog::query()
        ->where('auditable_type', 'user')
        ->where('event', AuditEvent::Updated->value)
        ->latest('id')
        ->firstOrFail();

    /*
     * `config('audit.strict')` is false, so `#[Hidden]` does NOT protect these —
     * only `User::$auditExclude` does. If that property is ever dropped, the
     * password hash lands in this row and this test is what says so.
     */
    expect($audit->new_values)->toHaveKey('name')
        ->and($audit->new_values)->not->toHaveKey('password')
        ->and($audit->old_values)->not->toHaveKey('password')
        ->and($audit->new_values)->not->toHaveKey('remember_token')
        ->and($audit->new_values)->not->toHaveKey('two_factor_secret')
        ->and($audit->new_values)->not->toHaveKey('two_factor_recovery_codes');

    // Belt and braces: the hash must not appear anywhere in the serialised row.
    expect(json_encode($audit->getAttributes()))->not->toContain($user->refresh()->password);
});

/*
|--------------------------------------------------------------------------
| Console
|--------------------------------------------------------------------------
*/

test('a write with no authenticated actor records no actor rather than no audit', function () {
    auditing();

    Designation::factory()->create(['name' => 'Unattributed']);

    $audit = AuditLog::query()->latest('id')->firstOrFail();

    expect($audit->user_id)->toBeNull()
        ->and($audit->actor_name)->toBeNull()
        ->and($audit->new_values['name'])->toBe('Unattributed');
});

test('console writes leave no trail when the console flag is off', function () {
    config(['audit.console' => false]);

    Designation::factory()->create();

    // The suite itself runs in console, which is why every other test here turns
    // the flag on. This is the production posture for seeders and commands.
    expect(AuditLog::query()->count())->toBe(0);
});

test('the recorder honours the console rule the package applies to model events', function () {
    config(['audit.console' => false]);

    $actor = userWithPermissions('admin.users.assign-roles');

    $this->actingAs($actor);

    Role::findOrCreate('auditor', 'web');

    app(UserService::class)->assignRoles($actor, ['auditor']);

    /*
     * The package's `RecordCustomAudit` listener checks nothing, so without
     * `AuditRecorder::enabled()` a seeder assigning a role would write an audit
     * while the user it created wrote none. The two paths must agree.
     */
    expect(AuditLog::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| What Eloquent cannot see
|--------------------------------------------------------------------------
*/

test('granting a role is recorded, with the roles named on both sides', function () {
    $actor = userWithPermissions('admin.users.assign-roles');

    $this->actingAs($actor);

    Role::findOrCreate('merchandiser', 'web');
    Role::findOrCreate('auditor', 'web');

    $subject = User::factory()->create();

    auditing();

    app(UserService::class)->assignRoles($subject, ['merchandiser']);
    app(UserService::class)->assignRoles($subject, ['auditor']);

    $audits = AuditLog::query()
        ->where('event', AuditEvent::RolesChanged->value)
        ->orderBy('id')
        ->get();

    /*
     * `model_has_roles` is a pivot spatie writes as raw rows — no model event
     * fires, so without `UserService::syncRoles()` recording it, "who made them
     * an admin" would be unanswerable. This is the escalation-sensitive action
     * documentation/admin.md §2.5 guards.
     */
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->old_values['roles'])->toBe([])
        ->and($audits[0]->new_values['roles'])->toBe(['merchandiser'])
        ->and($audits[1]->old_values['roles'])->toBe(['merchandiser'])
        ->and($audits[1]->new_values['roles'])->toBe(['auditor'])
        ->and($audits[0]->auditable_type)->toBe('user')
        ->and($audits[0]->auditable_id)->toBe($subject->id)
        ->and($audits[0]->actor_name)->toBe($actor->name);
});

test('re-saving the same role set records nothing', function () {
    $this->actingAs(userWithPermissions('admin.users.assign-roles'));

    Role::findOrCreate('merchandiser', 'web');

    $subject = User::factory()->create();

    auditing();

    app(UserService::class)->assignRoles($subject, ['merchandiser']);

    $before = AuditLog::query()->where('event', AuditEvent::RolesChanged->value)->count();

    // Opening the dialog and pressing save unchanged is the common case, not the
    // rare one — `recordChange()` is what keeps it out of the trail.
    app(UserService::class)->assignRoles($subject, ['merchandiser']);

    expect(AuditLog::query()->where('event', AuditEvent::RolesChanged->value)->count())
        ->toBe($before);
});

test('changing buyer access is recorded by name', function () {
    $this->actingAs(superAdmin());

    $subject = User::factory()->create();
    $buyer = Buyer::factory()->create(['name' => 'Walmart']);

    auditing();

    app(BuyerAccessService::class)->assign($subject, false, [$buyer->id]);

    $audit = AuditLog::query()
        ->where('event', AuditEvent::BuyerAccessChanged->value)
        ->latest('id')
        ->firstOrFail();

    // `buyer_user` is a pivot too, and buyer access decides which rows a person
    // can see at all (ARCHITECTURE.md §9.2) — so widening it must leave a trace.
    expect($audit->old_values['buyers'])->toBe([])
        ->and($audit->new_values['buyers'])->toBe(['Walmart'])
        ->and($audit->new_values['all_buyer_access'])->toBeFalse();
});

test('a successful sign-in is recorded against the user', function () {
    $user = User::factory()->create();

    auditing();

    event(new Login('web', $user, false));

    $audit = AuditLog::query()->where('event', AuditEvent::LoggedIn->value)->latest('id')->firstOrFail();

    expect($audit->auditable_type)->toBe('user')
        ->and($audit->auditable_id)->toBe($user->id)
        ->and($audit->new_values['employee_id'])->toBe($user->employee_id);
});

test('a failed sign-in records the employee id and never the password', function () {
    $password = 'a-secret-nobody-should-see-1!';

    auditing();

    event(new Failed('web', null, [
        'employee_id' => '99999',
        'password' => $password,
    ]));

    $audit = AuditLog::query()->where('event', AuditEvent::LoginFailed->value)->latest('id')->firstOrFail();

    /*
     * `Failed::$credentials` carries the submitted password. Passing that array
     * through would put a plaintext password in the trail — the same failure
     * `User::$auditExclude` prevents for the hash, arriving by another door.
     */
    expect($audit->new_values['employee_id'])->toBe('99999')
        ->and($audit->new_values['user_exists'])->toBeFalse()
        ->and(json_encode($audit->getAttributes()))->not->toContain($password)
        ->and($audit->new_values)->not->toHaveKey('password');

    // An unknown employee ID names no record, which is why `auditable` is nullable.
    expect($audit->auditable_type)->toBeNull()
        ->and($audit->auditable_id)->toBeNull();
});

test('importing a workbook records the import, not every row it holds', function () {
    Storage::fake('local');

    $buyer = Buyer::factory()->create(['name' => 'George']);

    $this->actingAs(bqsImporter($buyer));

    auditing();

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $buyer->id,
        'bqs_date' => '2026-09-01',
        'file' => bqsUpload(),
    ])->assertRedirect();

    $rows = BqsRow::query()->count();

    /*
     * **This is the whole point of the suppression** (ARCHITECTURE.md §9.3).
     * `bqs_rows`, `bqs_row_months` and `bqs_row_pack_sizes` are all `Audited`, so
     * without `BqsImportService::writeRow()` wrapping them a real 200-row workbook
     * would write ~5,800 audits inside the upload request. The import is recorded
     * once, by the `created` audit on its own `BqsImport` row.
     */
    expect($rows)->toBeGreaterThan(0)
        ->and(AuditLog::query()->where('auditable_type', 'bqs-row')->count())->toBe(0)
        ->and(AuditLog::query()->where('auditable_type', 'bqs-row-month')->count())->toBe(0)
        ->and(AuditLog::query()->where('auditable_type', 'bqs-row-pack-size')->count())->toBe(0)
        ->and(AuditLog::query()->where('auditable_type', 'bqs-import')->count())->toBe(1)
        ->and(AuditLog::query()->where('auditable_type', 'bqs-sheet')->count())->toBeGreaterThan(0);
});

test('an imported payload is recorded as changed but never copied', function () {
    Storage::fake('local');

    $buyer = Buyer::factory()->create(['name' => 'George']);

    $this->actingAs(bqsImporter($buyer));

    auditing();

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $buyer->id,
        'bqs_date' => '2026-09-01',
        'file' => bqsUpload(),
    ])->assertRedirect();

    $audits = AuditLog::query()
        ->whereIn('auditable_type', ['bqs-import', 'bqs-sheet'])
        ->get();

    /*
     * `payload` and `staged_rows` are excluded per model. Audited, `audit_logs`
     * would hold a second copy of every imported workbook — and the package's own
     * migration types the value columns `text`, which caps at 64 KB and would have
     * truncated silently on MySQL.
     */
    expect($audits)->not->toBeEmpty();

    foreach ($audits as $audit) {
        expect($audit->new_values)->not->toHaveKey('payload')
            ->and($audit->new_values)->not->toHaveKey('staged_rows')
            ->and($audit->old_values)->not->toHaveKey('payload');
    }
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.audit-logs.index'))->assertRedirect(route('login'));
});

test('the trail is refused without the permission', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    $this->get(route('admin.audit-logs.index'))->assertForbidden();
    $this->get(route('admin.audit-logs.history', ['type' => 'designation', 'id' => 1]))
        ->assertForbidden();
});

test('the list renders the trail newest first', function () {
    $this->actingAs(userWithPermissions('admin.audit-logs.view'));

    AuditLog::factory()->create(['created_at' => now()->subDay()]);
    AuditLog::factory()->create(['created_at' => now(), 'actor_name' => 'Most recent']);

    $this->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/audit-logs/index')
            ->has('auditLogs.data', 2)
            // The one surface whose default direction is `desc`; a log is read
            // from the top.
            ->where('filters.direction', 'desc')
            ->where('auditLogs.data.0.actor_name', 'Most recent'));
});

test('the model type filter is derived from the morph map, never hand-listed', function () {
    $this->actingAs(userWithPermissions('admin.audit-logs.view'));

    $this->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('modelTypes', count(Relation::morphMap())));
});

/*
|--------------------------------------------------------------------------
| The record-history endpoint
|--------------------------------------------------------------------------
*/

test('a record history returns every audit for that record', function () {
    $this->actingAs(userWithPermissions('admin.audit-logs.view'));

    $designation = Designation::factory()->create();

    AuditLog::factory()->count(2)->create([
        'auditable_type' => 'designation',
        'auditable_id' => $designation->id,
    ]);

    AuditLog::factory()->create(['auditable_type' => 'designation', 'auditable_id' => $designation->id + 999]);

    $this->getJson(route('admin.audit-logs.history', [
        'type' => 'designation',
        'id' => $designation->id,
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'audits');
});

test('the history endpoint refuses a class name where an alias belongs', function () {
    $this->actingAs(userWithPermissions('admin.audit-logs.view'));

    /*
     * The client names a record by morph alias, checked against the map before it
     * reaches a query. A class string is never resolved — which is the guard the
     * implementation this was ported from spells out above its own allow-list.
     */
    $this->getJson(route('admin.audit-logs.history', [
        'type' => Designation::class,
        'id' => 1,
    ]))->assertStatus(422);

    $this->getJson(route('admin.audit-logs.history', ['type' => 'not-a-model', 'id' => 1]))
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Who may read it
|--------------------------------------------------------------------------
*/

test('only the super-admin role is seeded the permission to read the trail', function () {
    $this->seed(RolePermissionSeeder::class);

    /*
     * `admin` would pick this up through its `admin.` prefix and `viewer` through
     * its `.view` suffix, so both need the explicit exclusion in
     * `RolePermissionSeeder::SUPER_ADMIN_ONLY`. The trail is every recorded value
     * in the application, across every buyer.
     */
    expect(Role::findByName(Role::SUPER_ADMIN)->hasPermissionTo('admin.audit-logs.view'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('admin.audit-logs.view'))->toBeFalse()
        ->and(Role::findByName('viewer')->hasPermissionTo('admin.audit-logs.view'))->toBeFalse();
});
