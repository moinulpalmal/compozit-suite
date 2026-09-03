<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| The password policy, on screen
|--------------------------------------------------------------------------
|
| The rules themselves are a feature test — `tests/Feature/PasswordPolicyTest.php`
| owns what the validator accepts. What no feature test can answer is the half
| of this change the user actually sees: **does the policy appear before they
| invent a password, and does it tick as they type?**
|
| That is not a rendering detail. The requirements were previously stated
| nowhere at all; the only thing resembling a hint was the `passwordrules`
| attribute, which is a Safari keychain instruction and invisible to a human.
| A user learned the policy by being rejected.
|
| `components/shared/password-policy-checklist.tsx` reads the rules from the
| `passwordPolicy` shared prop, so the regression this guards against is
| specific: the prop stops being shared (a `share()` edit, a controller that
| overrides it) and the checklist silently falls back to its loosest possible
| reading, showing an 8-character minimum and no complexity rules at all. Every
| assertion below therefore looks for a rule that only the *server's* policy
| supplies.
|
| Assertions key off `assertSee`, which reads visible text — the property that
| was missing.
|
*/

/** The requirement rows the shared policy produces, in render order. */
const POLICY_ROWS = [
    'At least 8 characters',
    'Upper and lower case letters',
    'At least one number',
    'At least one symbol',
];

/** The `uncompromised` note, which is stated rather than ticked. */
const POLICY_BREACH_NOTE = 'Checked against known breached passwords when you save.';

/**
 * Every requirement is on screen, plus the breach note.
 *
 * `Upper and lower case letters` is the load-bearing one: the fallback in
 * `usePasswordPolicy()` sets `mixedCase` false, so this row exists only when
 * the server's policy actually arrived.
 */
function assertPolicyVisible(object $page): object
{
    foreach (POLICY_ROWS as $row) {
        $page->assertSee($row);
    }

    return $page->assertSee(POLICY_BREACH_NOTE);
}

/**
 * The security settings page, reached the way a user reaches it.
 *
 * `security.edit` sits behind `RequirePassword`, and a browser test cannot skip
 * that with `withSession()` the way a feature test does — the session lives in
 * the server process. So the confirmation is actually performed. The password
 * is the factory's, which the policy would now reject on *save* but which
 * `current_password` confirmation does not validate against the policy at all.
 */
function visitSecuritySettings(): object
{
    // Visiting the destination first is what makes this work: `RequirePassword`
    // stores it as the intended URL, so confirming lands back here rather than
    // on the dashboard.
    return visit('/settings/security')
        ->fill('#password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->waitForText('Update password');
}

test('the policy is on screen before anything is typed, on the security settings page', function () {
    $this->actingAs(User::factory()->create());

    assertPolicyVisible(visitSecuritySettings())->assertNoJavaScriptErrors();
});

test('the checklist ticks the requirements a typed password meets', function () {
    $this->actingAs(User::factory()->create());

    $page = visitSecuritySettings();

    // Long enough and mixed case, but no digit and no symbol — so the list must
    // be in a *partly* satisfied state rather than all one way, which is what
    // proves it is reading the field rather than rendering a constant.
    $page->fill('#password', 'AbcdefghIJ')
        ->assertNoJavaScriptErrors();

    expect(policyRowsMet($page))->toBe([
        'At least 8 characters' => true,
        'Upper and lower case letters' => true,
        'At least one number' => false,
        'At least one symbol' => false,
    ]);

    $page->fill('#password', 'Ab1!cdef');

    expect(policyRowsMet($page))->toBe([
        'At least 8 characters' => true,
        'Upper and lower case letters' => true,
        'At least one number' => true,
        'At least one symbol' => true,
    ]);
});

test('the policy is on screen when an administrator sets someone elses password', function () {
    $this->actingAs(userWithPermissions(
        'admin.users.view',
        'admin.users.reset-password',
    ));

    User::factory()->create(['name' => 'Rezaul Karim']);

    $page = visit('/admin/users');

    // By label, not by `data-test`: the attribute is on every row's button and
    // the list carries more than one user.
    assertPolicyVisible($page->click('[aria-label="Set password for Rezaul Karim"]')
        ->waitForText('New password'))
        ->assertNoJavaScriptErrors();
});

test('the policy is on screen when an administrator creates a user', function () {
    $this->actingAs(userWithPermissions(
        'admin.users.view',
        'admin.users.create',
    ));

    $page = visit('/admin/users');

    assertPolicyVisible($page->click('New user')->waitForText('Password'))
        ->assertNoJavaScriptErrors();
});

test('the policy is on screen on the password reset page', function () {
    $user = User::factory()->create();

    $token = Password::createToken($user);

    $page = visit("/reset-password/{$token}?email=".urlencode($user->email));

    assertPolicyVisible($page)->assertNoJavaScriptErrors();
});

/**
 * Which requirement rows are currently ticked.
 *
 * Read off the row's tone class rather than the icon: the icon is an inline
 * SVG with no accessible text, and `text-success` is the thing a user sees
 * change.
 */
function policyRowsMet(object $page): array
{
    /** @var array<string, bool> $rows */
    $rows = (array) $page->script(<<<'JS'
        (() => Object.fromEntries(
            [...document.querySelectorAll('[data-test="password-policy"] li')]
                .map((li) => [
                    li.querySelector('span')?.textContent?.trim() ?? '',
                    li.className.includes('text-success'),
                ]),
        ))();
    JS);

    return $rows;
}
