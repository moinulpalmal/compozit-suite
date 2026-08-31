<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyers the Admin module administers and every buyer-owned record is scoped by.
 *
 * Shaped after `designations`: `status` is a single character (`A`/`I`), not a
 * boolean and not `deleted_at`. Deactivating retires a buyer from the pickers
 * while leaving its orders and its access grants intact; deleting is a separate
 * verb refused by `Admin\BuyerService::deletionBlocker()` once anything factual
 * references it. See ARCHITECTURE.md §9.3.1.
 *
 * No index beyond the two unique constraints. A unique constraint already *is*
 * an index (ARCHITECTURE.md §6.3), and `status` has two values — too low in
 * cardinality to be worth one on its own.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();

            /*
             * The short code a buyer is known by on orders and reports. Unique
             * *when present*: both MySQL and SQLite allow repeated NULLs in a
             * unique index, so "no code yet" stays legal — which is what lets
             * rows imported from the old system land before codes are assigned —
             * while two buyers sharing a code does not.
             */
            $table->string('code', 20)->nullable()->unique();

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
        Schema::dropIfExists('buyers');
    }
};
