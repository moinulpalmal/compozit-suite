<?php

namespace App\Services\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Grants and revokes buyer access — the write half of ARCHITECTURE.md §9.2.
 *
 * There is one write path for this fact, and it is here. The users screen calls
 * it; if a per-buyer screen is ever built it calls this too rather than growing
 * a second copy of the guards.
 *
 * Those guards live in a service rather than `Admin\UserPolicy` for the reason
 * `UserService` records: `Gate::before` grants a super admin every ability, so a
 * policy denial is bypassed for exactly the account a privilege guard exists to
 * bind.
 */
class BuyerAccessService
{
    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Replace a user's buyer access.
     *
     * **The flag and the pivot are mutually exclusive.** Granting all-access
     * detaches every row, because keeping them underneath the flag recreates
     * precisely the ambiguity this design removed: a row that exists because of
     * the wildcard is indistinguishable from one somebody chose, and revoking
     * the wildcard can then no longer tell which to keep.
     *
     * @param  list<int>  $buyerIds
     */
    public function assign(User $user, bool $allBuyerAccess, array $buyerIds): void
    {
        DB::transaction(function () use ($user, $allBuyerAccess, $buyerIds): void {
            /*
             * Captured before the write, because the audit below is the only record
             * of it. `buyer_user` is a pivot: `sync()` writes raw rows and fires no
             * model event, so widening somebody's visibility is invisible to the
             * trail unless it is recorded here (ARCHITECTURE.md §9.3).
             *
             * Names rather than ids, and sorted — the trail is read by a person, and
             * a stable order is what lets `recordChange()` tell a real change from
             * the database returning the same set in a different order.
             */
            $before = $this->accessSnapshot($user);

            $user->buyers()->sync($allBuyerAccess ? [] : $buyerIds);

            /*
             * `forceFill`, because `all_buyer_access` is deliberately not
             * mass-assignable: this service is its only writer, so the user form
             * cannot widen someone's visibility as a side effect of editing a
             * phone number.
             */
            $user->forceFill(['all_buyer_access' => $allBuyerAccess])->save();

            $this->audits->recordChange(
                $user,
                AuditEvent::BuyerAccessChanged,
                $before,
                $this->accessSnapshot($user->refresh()),
            );
        });
    }

    /**
     * A user's buyer access as the trail records it.
     *
     * @return array{all_buyer_access: bool, buyers: list<string>}
     */
    private function accessSnapshot(User $user): array
    {
        $names = $user->buyers()->pluck('name')->all();

        sort($names);

        return [
            'all_buyer_access' => (bool) $user->all_buyer_access,
            'buyers' => $names,
        ];
    }

    /**
     * The reason the actor may not change this user's buyer access, if there is one.
     *
     * Two rules, both mirroring how roles are guarded:
     *
     * - Nobody edits their own access. `roleAssignmentBlocker()` refuses the same
     *   thing for the same reason — otherwise the permission to grant access is
     *   the permission to grant it to yourself, with no second pair of eyes.
     * - Nobody grants access they do not hold, which is the buyer-scope analogue
     *   of `RoleAssignmentRules::assignableRoleRule()` refusing to let a
     *   non-super-admin hand out `super-admin`.
     *
     * @param  list<int>  $buyerIds
     */
    public function assignmentBlocker(User $user, bool $allBuyerAccess, array $buyerIds): ?string
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            return __('You must be signed in to change buyer access.');
        }

        if ($actor->is($user)) {
            return __('You cannot change your own buyer access. Ask another administrator.');
        }

        if ($actor->seesAllBuyers()) {
            return null;
        }

        if ($allBuyerAccess) {
            return __('Only someone with access to every buyer may grant it.');
        }

        $beyond = array_diff($buyerIds, $actor->accessibleBuyerIds());

        if ($beyond !== []) {
            return trans_choice(
                '{1} You cannot grant access to a buyer you cannot see yourself.'
                .'|[2,*] You cannot grant access to :count buyers you cannot see yourself.',
                count($beyond),
                ['count' => count($beyond)],
            );
        }

        return null;
    }
}
