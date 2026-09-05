<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites the RBAC pivots for the morph map introduced alongside `audit_logs`.
 *
 * **This migration is what stops the audit trail from locking everybody out.**
 *
 * `audit_logs` is this application's first polymorphic table, and it stores
 * `auditable_type`. Left as class names, a single rename or module re-scope —
 * which ARCHITECTURE.md §12 lists as an expected event — orphans every audit row
 * that named the moved class. So `AppServiceProvider::configureMorphMap()`
 * registers an enforced morph map, and `auditable_type` stores a short alias.
 *
 * The catch is that a morph map is global. `spatie/laravel-permission` writes
 * `model_has_roles.model_type` with the same `Model::getMorphClass()`, so
 * registering the map changes what *that* column means: new rows would say
 * `user` while every existing row says `App\Models\User`, and
 * `HasRoles::scopeRole()` matches on it. Every account — the super admin
 * included — would silently hold no roles, which reads as a catastrophic
 * permissions bug and is really a two-line data problem.
 *
 * The values are written as **literals rather than `User::class`** on purpose. A
 * data migration describes a state the database was actually in; if the class is
 * ever moved, `User::class` would stop matching the rows this was written to fix,
 * while the literal still names them correctly.
 *
 * `model_has_permissions` is empty in every environment today — permissions are
 * granted through roles, never directly — but it is rewritten anyway, because a
 * direct grant made later would be just as broken and just as invisible.
 */
return new class extends Migration
{
    /**
     * The class name these tables held before the morph map, and the alias the
     * map replaces it with. Must agree with `AppServiceProvider::MORPH_MAP`.
     */
    private const string LEGACY_USER_TYPE = 'App\Models\User';

    private const string USER_MORPH_ALIAS = 'user';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rewrite(self::LEGACY_USER_TYPE, self::USER_MORPH_ALIAS);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rewrite(self::USER_MORPH_ALIAS, self::LEGACY_USER_TYPE);
    }

    /**
     * Point every pivot row at the given morph type.
     *
     * Idempotent by construction — it matches on the value it is replacing, so
     * re-running it is a no-op rather than a corruption.
     */
    private function rewrite(string $from, string $to): void
    {
        /** @var array<string, string> $tables */
        $tables = config('permission.table_names');

        foreach (['model_has_roles', 'model_has_permissions'] as $key) {
            DB::table($tables[$key])
                ->where('model_type', $from)
                ->update(['model_type' => $to]);
        }
    }
};
