<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per batch of files somebody uploaded to the merchandising document library.
 *
 * **Nothing here is parsed.** Unlike `po_imports` and `bqs_imports`, which exist to
 * record what a parser made of a file, this table records only that a file arrived and
 * what the uploader called it. `file_type` is a label chosen on the form, not a format
 * detected from the bytes — see `App\Enums\Merchandising\DocumentType`.
 *
 * **`buyer_id` is nullable, and that is a decision.** A size chart or a TNA formula
 * often concerns no single buyer, and a null here means "visible to everyone with the
 * view permission" rather than "visible to nobody". `App\Concerns\BuyerScopedOrGlobal`
 * is what makes that true; `BuyerScoped` would get it backwards, because `NULL` never
 * matches an `IN` list. See ARCHITECTURE.md §9.2.
 *
 * **Indexing:** one composite `(buyer_id, created_at)` — the scope's own predicate is
 * the leading column and the list's default order is the second, which is exactly the
 * shape ARCHITECTURE.md §6.3 asks for. `file_type` is deliberately *not* indexed: five
 * values across the whole table is not selective enough to beat a scan, and a flag
 * belongs inside a composite or nowhere.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_uploads', function (Blueprint $table): void {
            $table->id();

            /* Nullable on purpose — see the docblock above. */
            $table->foreignId('buyer_id')->nullable()->constrained('buyers')->cascadeOnDelete();

            $table->string('file_type', 20);

            /* What the uploader called the batch. Optional: the files have names. */
            $table->string('title', 255)->nullable();
            $table->text('note')->nullable();

            /*
             * How many files the batch holds, denormalised.
             *
             * A `withCount` alias cannot go in `SORTABLE` or `FILTERABLE` — it needs
             * `HAVING` and a different path (ARCHITECTURE.md §8.6) — so the count is
             * stored precisely so the list can sort on it. `DocumentLibraryService` is
             * the only writer, and it rewrites the value on every add and delete.
             */
            $table->unsignedInteger('file_count')->default(0);

            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();

            /*
             * A batch is updated: replacing or deleting one of its files rewrites
             * `file_count`. `ActorObserver` stamps both columns (ARCHITECTURE.md §9.3).
             */
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['buyer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_uploads');
    }
};
