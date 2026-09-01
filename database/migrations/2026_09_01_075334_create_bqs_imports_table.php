<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per uploaded BQS workbook.
 *
 * The audit trail behind every imported BQS: the file as it arrived, the header map
 * the reader resolved out of its two header rows, and every warning raised — including
 * for a workbook whose upload was ultimately skipped. Keeping the file here and the
 * BQS itself on `bqs_sheets` is what lets a *skipped* upload still leave a record
 * without creating a revision; see `documentation/merchandising.md`.
 *
 * It is buyer-owned like the sheets it produces, so a user only sees the imports for
 * buyers they have access to (ARCHITECTURE.md §9.2).
 *
 * **Indexing:** `unique(buyer_id, source_hash)` **is** the index (ARCHITECTURE.md
 * §6.3) and is what makes a byte-identical re-upload idempotent. Nothing else is
 * indexed — this table is reached from a sheet, not searched.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bqs_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();

            /*
             * The BQS date, **entered on the upload form** and required.
             *
             * The workbook itself carries no date of any kind — not a document date,
             * not a revision date — so like `buyer_id` it is chosen by the uploader
             * rather than read out of the file. It is held here as well as on the
             * sheet because an upload that stages a collision must remember it until
             * the uploader answers.
             */
            $table->date('bqs_date');

            $table->string('source_file_name', 255);

            /*
             * Where the uploaded workbook was stored, relative to the configured disk.
             * Nullable because `bqs-import.storage.retain_original` may be off, in
             * which case the header map and warnings below are all that survive.
             */
            $table->string('stored_path', 500)->nullable();

            $table->string('detected_file_type', 10);
            $table->string('sheet_name', 100);

            /*
             * A hash of the resolved band+leaf header set, excluding the dynamic
             * bands. A fingerprint nobody has seen before means George changed the
             * template — the signal that the reader is now quietly mapping less than
             * it used to.
             */
            $table->string('header_fingerprint', 12);

            $table->unsignedInteger('row_count');
            $table->string('parse_status', 20);
            $table->string('source_hash', 64);

            /* The resolved header map, the unmapped columns, and every warning. */
            $table->json('payload');

            /* Rows held back for a decision — see `bqs_sheets` and the resolve route. */
            $table->json('staged_rows')->nullable();

            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();

            /*
             * An import row is *updated* — `staged_rows` is written when a collision is
             * held back and cleared when it is answered — so `ActorObserver` stamps
             * this on the way through. `po_imports` gained the same column, in a
             * follow-up migration, for exactly this reason.
             */
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['buyer_id', 'source_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bqs_imports');
    }
};
