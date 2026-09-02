<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per uploaded purchase-order document.
 *
 * This is the audit trail behind every imported order: the file as it arrived, what
 * the parser made of it, and every warning it raised — including for the orders that
 * failed. Keeping the whole parse result here is what makes a `failed` purchase order
 * diagnosable rather than merely reported; see `documentation/merchandising.md`.
 *
 * It is buyer-owned like the orders it produces, so a user only sees the imports for
 * buyers they have access to (ARCHITECTURE.md §9.2).
 *
 * **No index beyond the two foreign keys.** InnoDB indexes a `constrained()` column
 * automatically (ARCHITECTURE.md §6.3), and nothing filters this table on anything
 * else — it is reached from a purchase order, not searched.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('po_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();

            $table->string('source_file_name', 255);

            /*
             * Where the uploaded document was stored, relative to the configured
             * disk. Nullable because `po-parser.storage.retain_original` may be off,
             * in which case the parse result below is all that survives.
             */
            $table->string('stored_path', 500)->nullable();

            $table->string('detected_file_type', 10);
            $table->string('template_fingerprint', 12);
            $table->unsignedInteger('page_count');
            $table->unsignedInteger('po_count');

            $table->string('parse_status', 20);
            $table->decimal('confidence', 4, 3);

            /* The complete ParseResultDto, warnings included. */
            $table->json('payload');

            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_imports');
    }
};
