<?php

use App\Models\Merchandising\PoImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders that collide with one already held, waiting on a decision.
 *
 * A document holds up to fifty orders (`po-parser.limits.max_pos_per_file`), so the
 * question "is this a reissue or a stale re-upload?" cannot be asked inside the upload
 * request — there may be fifty of them. The import splits in two: orders that collide
 * with nothing are written immediately, and the rest are staged here until the uploader
 * answers. See `documentation/merchandising.md` §3.5.
 *
 * **What is staged is the rows, not the parse.** None of the parser's DTOs can be
 * rebuilt from an array, and writing hydration for nineteen of them to serve one flow
 * would be a poor trade. `PurchaseOrderImportService::orderAttributes()` produces the
 * insertable row, and that is what is kept — so confirming costs a write, not a second
 * sixty-second run through LibreOffice.
 *
 * **There is deliberately no companion status column.** An import is pending exactly
 * when this column is not null; that is the whole of the state, and it is already
 * carried by the data. A second column would only add a way for the two to disagree.
 * {@see PoImport::scopePending()} expresses it.
 *
 * No index (ARCHITECTURE.md §6.3): the only query is one user's latest pending import,
 * already bounded by `buyer_id` and `inserted_by`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('po_imports', function (Blueprint $table): void {
            $table->json('staged_orders')->nullable()->after('payload');

            /*
             * The table stopped being insert-only with the column above: an import
             * is written once, then written again when its conflicts are answered.
             * `ActorObserver` (ARCHITECTURE.md §9.3) stamps this on every update and
             * is already registered on the model — without the column it fails on
             * the second write instead of the first, which is the worst place to
             * discover it.
             */
            $table->foreignId('last_updated_by')->nullable()->after('inserted_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('po_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_updated_by');
            $table->dropColumn('staged_orders');
        });
    }
};
