<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The job titles the Admin module administers and `users.designation_id` points at.
 *
 * `status` is a single character (`A`/`I`), not a boolean and not `deleted_at`:
 * deactivating retires a title from the pickers, deleting removes it and is
 * refused while any user still holds it.
 *
 * This was the first char-flagged table. It later became the house convention —
 * `App\Enums\RecordStatus` with `App\Concerns\HasStatus` — and `users.approved`
 * was migrated to match it rather than the other way round. See
 * ARCHITECTURE.md §9.3.1.
 *
 * No index beyond the two unique constraints. A unique constraint already *is*
 * an index (ARCHITECTURE.md §6.3), the table is small, and `status` has two
 * values — too low in cardinality to be worth indexing on its own.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();

            /*
             * Unique *when present*. Both MySQL and SQLite allow repeated NULLs
             * in a unique index, so "no short form yet" stays legal while two
             * designations sharing a code does not.
             */
            $table->string('short_form', 50)->nullable()->unique();

            $table->string('status', 1)->default(RecordStatus::Active->value);
            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
