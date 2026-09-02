<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per file inside a document upload.
 *
 * **No `buyer_id` and no buyer scope.** A file reaches its buyer through its batch,
 * which is the ARCHITECTURE.md §9.2 rule for a child table — `po_line_items` and
 * `bqs_rows` are the precedents. The consequence is that route-model binding must
 * never resolve one of these on its own: every per-file route is nested under its
 * `document_uploads` parent and declared with `->scopeBindings()`, so the file is
 * found through the parent relationship and inherits the parent's scope.
 *
 * `original_name` is what the uploader called the file and is **never** part of
 * `stored_path` — the path is built from a ULID, so a filename can neither collide
 * with another upload's nor carry a traversal segment onto the disk.
 *
 * **Indexing:** the foreign key only, which InnoDB creates itself (§6.3), so nothing
 * is declared here. `file_hash` is deliberately **not** unique: a document library
 * legitimately holds the same bytes twice — the same size chart filed under two
 * types, or re-sent by a second person — and it is stored to answer "is this the same
 * file?", not to refuse the second copy. That is the opposite of `bqs_imports`, where
 * a byte-identical re-upload is a no-op worth detecting.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_upload_id')->constrained('document_uploads')->cascadeOnDelete();

            $table->string('original_name', 255);
            $table->string('stored_path', 500);
            $table->string('extension', 10);

            /*
             * What the browser claimed the file was. Held for display and for the
             * preview response's `Content-Type`; it is not trusted as a validation
             * signal — the extension allow-list in `DocumentUploadStoreRequest` is.
             */
            $table->string('mime_type', 150);

            $table->unsignedBigInteger('size_bytes');
            $table->string('file_hash', 64);

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
        Schema::dropIfExists('document_files');
    }
};
