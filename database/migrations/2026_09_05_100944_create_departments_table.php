<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer's own merchandise department — `GIRLSWEAR`, `BOYSWEAR`, `MENSWEAR`.
 *
 * **Not an internal org unit.** This is the buyer's classification of what they
 * buy, which is why every row belongs to exactly one buyer and why the same name
 * appears once per buyer. See ARCHITECTURE.md §9.4.
 *
 * Shaped after `designations` and `buyers`: `status` is a single character
 * (`A`/`I`), not a boolean and not `deleted_at`. Deactivating retires a
 * department from the pickers while leaving it in place; deleting is a separate
 * verb routed through `Admin\DepartmentService::deletionBlocker()`. See
 * ARCHITECTURE.md §9.3.1.
 *
 * `buyer_id` is `restrictOnDelete`, not `cascadeOnDelete`: a department is a
 * *fact* about a buyer, and ARCHITECTURE.md §5 draws that line explicitly —
 * access grants cascade because they are not facts. The database refuses, and
 * `Admin\BuyerService::deletionBlocker()` explains why in a sentence, because an
 * integrity-constraint exception is a stack trace rather than an explanation
 * (ARCHITECTURE.md §9.4).
 *
 * **No index beyond the two unique constraints.** A unique constraint already
 * *is* an index (ARCHITECTURE.md §6.3), and `unique(buyer_id, name)` leads with
 * `buyer_id` — precisely the column `App\Models\Scopes\BuyerScope` seeks on with
 * `whereIn('buyer_id', …)`, so a separate index for the foreign key would be a
 * duplicate on MySQL, which indexes a `constrained()` column automatically.
 * `status` has two values, far too low in cardinality to earn one alone.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);

            /*
             * The short code the department is known by. Unique *per buyer* and
             * only *when present*: both MySQL and SQLite allow repeated NULLs in
             * a unique index, so "no code yet" stays legal while two departments
             * of one buyer sharing a code does not.
             */
            $table->string('code', 50)->nullable();

            $table->string('status', 1)->default(RecordStatus::Active->value);
            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /*
             * Scoped to the buyer, not global. Two buyers both having a
             * "KIDSWEAR" is the normal case, not a collision.
             */
            $table->unique(['buyer_id', 'name']);
            $table->unique(['buyer_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
