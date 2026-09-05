<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Password;

/*
|--------------------------------------------------------------------------
| The password policy
|--------------------------------------------------------------------------
|
| One definition, in `config/auth.php`, assembled into `Password::defaults()`
| by `AppServiceProvider` and shared with the frontend by
| `HandleInertiaRequests`. These tests pin all three ends of that.
|
| The minimum is **8**, not 12. It was 12, and only in production — the rule
| read `app()->isProduction() ? Password::min(12)->… : null`, so every other
| environment fell through to Laravel's bare `min(8)` and the suite proved
| nothing about the policy the application actually enforces. The environment
| branch is gone. If one reappears, the last test in this file is the one that
| fails.
|
| `uncompromised()` is a live Have I Been Pwned lookup. `tests/Pest.php` fakes
| that endpoint for the whole suite; the breach test below overrides the fake
| with a "found" response, which is the only way to exercise the rule without
| depending on the real corpus.
|
*/

/**
 * Attempt a password change through the real validation path.
 */
function attemptPasswordChange(string $password): TestResponse
{
    $user = User::factory()->create();

    return test()
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
}

test('an eight-character compliant password is accepted', function () {
    $password = 'Ab1!cdef';

    expect(strlen($password))->toBe(8);

    attemptPasswordChange($password)->assertSessionHasNoErrors();
});

test('a seven-character password is rejected', function () {
    $password = 'Ab1!cde';

    expect(strlen($password))->toBe(7);

    attemptPasswordChange($password)->assertSessionHasErrors('password');
});

test('the complexity rules are still enforced at eight characters', function (string $password) {
    attemptPasswordChange($password)->assertSessionHasErrors('password');
})->with([
    'no uppercase' => 'ab1!cdef',
    'no lowercase' => 'AB1!CDEF',
    'no number' => 'Abc!defg',
    'no symbol' => 'Ab1cdefg',
]);

test('a breached password is rejected', function () {
    $password = 'Ab1!cdef';

    fakeBreachedPasswordLookup($password);

    attemptPasswordChange($password)->assertSessionHasErrors('password');
});

test('an accepted password is what gets stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Ab1!cdef',
            'password_confirmation' => 'Ab1!cdef',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('Ab1!cdef', $user->refresh()->password))->toBeTrue();
});

test('the policy comes from config and carries no environment branch', function () {
    expect(config('auth.password_policy'))->toBe([
        'min_length' => 8,
        'mixed_case' => true,
        'letters' => true,
        'numbers' => true,
        'symbols' => true,
        'uncompromised' => true,
    ]);

    // `toPasswordRulesString()` is the closest thing to a public read-out of a
    // built `Password` rule, and it reflects the whole policy. The suite is not
    // running in production, so a rule of this shape can only mean the
    // environment branch is gone.
    expect(Password::defaults()->toPasswordRulesString())
        ->toContain('minlength: 8')
        ->toContain('required: lower')
        ->toContain('required: upper')
        ->toContain('required: digit')
        ->toContain('required: special');

    expect(app()->isProduction())->toBeFalse();
});

test('the policy is shared with every page, including guest pages', function () {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->where('passwordPolicy.minLength', 8)
            ->where('passwordPolicy.mixedCase', true)
            ->where('passwordPolicy.numbers', true)
            ->where('passwordPolicy.symbols', true)
            ->where('passwordPolicy.uncompromised', true)
            ->has('passwordPolicy.hint'),
        );
});
