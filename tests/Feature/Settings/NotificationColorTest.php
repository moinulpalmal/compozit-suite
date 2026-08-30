<?php

use App\Enums\RecordStatus;
use App\Models\Settings\NotificationColor;
use App\Models\User;
use App\Services\Settings\NotificationColorService;

/*
|--------------------------------------------------------------------------
| Notification colours
|--------------------------------------------------------------------------
|
| The Settings module's first master-data surface. The paginate/sort/filter
| contract is covered once for every list in `tests/Feature/ListBehaviourTest.php`
| — this file holds only what is specific to this surface: its permissions, its
| two unique constraints, the hex format and its normalisation, and actor
| stamping.
|
*/

/** A colour the seeded data will never contain by accident. */
const A_COLOUR = '#A1B2C3';

/**
 * The payload the create form submits, with overrides applied.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function colorPayload(array $overrides = []): array
{
    return [
        'name' => 'Urgent',
        'color_code' => A_COLOUR,
        'retention_days' => 30,
        'status' => RecordStatus::Active->value,
        ...$overrides,
    ];
}

describe('permissions', function () {
    test('the list is refused without settings.master-data.view', function () {
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.master-data.notification-colors.index'))
            ->assertForbidden();
    });

    test('each write action needs its own permission', function (string $permission) {
        // Holding every *other* permission is what proves the one under test is
        // the one doing the work.
        $this->actingAs(userWithPermissions(
            ...array_diff(
                ['settings.master-data.create', 'settings.master-data.update', 'settings.master-data.delete'],
                [$permission],
            ),
        ));

        $color = NotificationColor::factory()->create();

        $response = match ($permission) {
            'settings.master-data.create' => $this->post(
                route('settings.master-data.notification-colors.store'),
                colorPayload(),
            ),
            'settings.master-data.update' => $this->put(
                route('settings.master-data.notification-colors.update', $color),
                colorPayload(['name' => 'Renamed']),
            ),
            default => $this->delete(
                route('settings.master-data.notification-colors.destroy', $color),
            ),
        };

        $response->assertForbidden();
    })->with([
        'settings.master-data.create',
        'settings.master-data.update',
        'settings.master-data.delete',
    ]);
});

describe('creating', function () {
    test('a colour is created and reported', function () {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        $response = $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(),
        );

        assertToast($response, 'success');

        $this->assertDatabaseHas('notification_colors', [
            'name' => 'Urgent',
            'color_code' => A_COLOUR,
            'retention_days' => 30,
            'status' => RecordStatus::Active->value,
        ]);
    });

    test('a lowercase hex is stored uppercase', function () {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['color_code' => '#a1b2c3']),
        );

        $this->assertDatabaseHas('notification_colors', ['color_code' => A_COLOUR]);
    });

    test('a hex with no leading hash is accepted', function () {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['color_code' => 'a1b2c3']),
        );

        $this->assertDatabaseHas('notification_colors', ['color_code' => A_COLOUR]);
    });

    /*
     * The point of normalising in `prepareForValidation()` rather than in a
     * mutator: the unique rule has to compare the *normalised* value. With a
     * mutator this submission would pass validation and then collide at the
     * driver as a 500 instead of a field error.
     */
    test('a lowercase hex collides with the stored uppercase one', function () {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        NotificationColor::factory()->create(['color_code' => A_COLOUR]);

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['color_code' => strtolower(A_COLOUR)]),
        )->assertSessionHasErrors('color_code');

        expect(NotificationColor::count())->toBe(1);
    });

    test('a duplicate name is a field error', function () {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        NotificationColor::factory()->create(['name' => 'Urgent']);

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['color_code' => '#0F0F0F']),
        )->assertSessionHasErrors('name');
    });

    test('a malformed colour is refused', function (string $colorCode) {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['color_code' => $colorCode]),
        )->assertSessionHasErrors('color_code');

        expect(NotificationColor::count())->toBe(0);
    })->with([
        'a colour name' => 'red',
        'the three-digit short form' => '#FFF',
        'non-hex characters' => '#GGGGGG',
        'too long' => '#AABBCCDD',
        'empty' => '',
    ]);

    test('retention is bounded', function (mixed $retentionDays) {
        $this->actingAs(userWithPermissions('settings.master-data.create'));

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['retention_days' => $retentionDays]),
        )->assertSessionHasErrors('retention_days');
    })->with([
        'zero' => 0,
        'negative' => -1,
        'past ten years' => 3651,
        'fractional' => 1.5,
        'not a number' => 'thirty',
    ]);
});

describe('updating', function () {
    test('a colour is updated and reported', function () {
        $this->actingAs(userWithPermissions('settings.master-data.update'));

        $color = NotificationColor::factory()->create();

        $response = $this->put(
            route('settings.master-data.notification-colors.update', $color),
            colorPayload(['name' => 'Renamed', 'retention_days' => 7]),
        );

        assertToast($response, 'success');

        expect($color->fresh())
            ->name->toBe('Renamed')
            ->color_code->toBe(A_COLOUR)
            ->retention_days->toBe(7);
    });

    /*
     * `Rule::unique()->ignore()` — without it, saving a row without touching
     * either unique field would fail on the row's own values.
     */
    test('a colour keeps its own name and hex', function () {
        $this->actingAs(userWithPermissions('settings.master-data.update'));

        $color = NotificationColor::factory()->create([
            'name' => 'Urgent',
            'color_code' => A_COLOUR,
        ]);

        $this->put(
            route('settings.master-data.notification-colors.update', $color),
            colorPayload(['retention_days' => 90]),
        )->assertSessionHasNoErrors();

        expect($color->fresh()->retention_days)->toBe(90);
    });

    test('a colour cannot take another row\'s hex', function () {
        $this->actingAs(userWithPermissions('settings.master-data.update'));

        NotificationColor::factory()->create(['color_code' => A_COLOUR]);
        $color = NotificationColor::factory()->create(['color_code' => '#0F0F0F']);

        $this->put(
            route('settings.master-data.notification-colors.update', $color),
            colorPayload(['name' => 'Anything', 'color_code' => A_COLOUR]),
        )->assertSessionHasErrors('color_code');
    });

    /*
     * Retiring is not deleting — ARCHITECTURE.md §9.3.1. The row stays, and
     * `assignableOptions()` is what stops offering it.
     */
    test('deactivating keeps the row and removes it from the picker', function () {
        $this->actingAs(userWithPermissions('settings.master-data.update'));

        $color = NotificationColor::factory()->create();

        $this->put(
            route('settings.master-data.notification-colors.update', $color),
            colorPayload([
                'name' => $color->name,
                'color_code' => $color->color_code,
                'status' => RecordStatus::Inactive->value,
            ]),
        );

        expect($color->fresh()->isActive())->toBeFalse();
        $this->assertDatabaseCount('notification_colors', 1);

        expect(app(NotificationColorService::class)->assignableOptions())
            ->toBeEmpty();
    });

    test('a retired colour is still offered to whatever already holds it', function () {
        $this->actingAs(userWithPermissions('settings.master-data.view'));

        $retired = NotificationColor::factory()->inactive()->create();

        expect(app(NotificationColorService::class)->assignableOptions([$retired->id]))
            ->toHaveCount(1);
    });
});

describe('deleting', function () {
    /*
     * Unconditional, and that is the current design rather than an oversight:
     * nothing references a colour until `notifications` is built. When it is,
     * this test grows a sibling asserting the refusal — see
     * documentation/settings.md §3.5.
     */
    test('a colour is deleted and reported', function () {
        $this->actingAs(userWithPermissions('settings.master-data.delete'));

        $color = NotificationColor::factory()->create();

        $response = $this->delete(
            route('settings.master-data.notification-colors.destroy', $color),
        );

        assertToast($response, 'success');

        $this->assertDatabaseCount('notification_colors', 0);
    });
});

describe('audit stamping', function () {
    test('the actor is recorded on create and on update', function () {
        $creator = userWithPermissions('settings.master-data.create', 'settings.master-data.update');

        $this->actingAs($creator);

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(),
        );

        $color = NotificationColor::sole();

        expect($color->inserted_by)->toBe($creator->id)
            ->and($color->last_updated_by)->toBeNull();

        $editor = userWithPermissions('settings.master-data.update');

        $this->actingAs($editor)->put(
            route('settings.master-data.notification-colors.update', $color),
            colorPayload(['retention_days' => 60]),
        );

        expect($color->fresh())
            ->inserted_by->toBe($creator->id)
            ->last_updated_by->toBe($editor->id);
    });

    test('the audit columns are not mass assignable', function () {
        $intruder = User::factory()->create();

        $this->actingAs(userWithPermissions('settings.master-data.create'));

        $this->post(
            route('settings.master-data.notification-colors.store'),
            colorPayload(['inserted_by' => $intruder->id]),
        );

        expect(NotificationColor::sole()->inserted_by)->not->toBe($intruder->id);
    });
});

describe('the picker', function () {
    /*
     * A list and its picker are different queries — ARCHITECTURE.md §8.6.
     * Paginating the screen must never truncate the dropdown.
     */
    test('assignable options are not paginated', function () {
        NotificationColor::factory()->count(30)->create();

        expect(app(NotificationColorService::class)->assignableOptions())
            ->toHaveCount(30);
    });
});
