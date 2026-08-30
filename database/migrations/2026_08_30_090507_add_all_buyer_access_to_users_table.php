<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this user sees every buyer, present and future.
 *
 * A *power* flag, like `approval_authority` — not an active flag, which is what
 * `users.status` is (ARCHITECTURE.md §9.3.1). It is the whole of the "all buyer
 * access" grant: a user carrying it has no `buyer_user` rows and needs none, so
 * a buyer created a minute from now is visible immediately with nothing to
 * synchronise.
 *
 * Not indexed. Two values is far too low in cardinality to beat a scan, and no
 * query filters on it — `BuyerScope` reads it off the already-loaded actor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('all_buyer_access')->default(false)->after('approval_authority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('all_buyer_access');
        });
    }
};
