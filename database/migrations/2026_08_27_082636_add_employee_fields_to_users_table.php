<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `users` into the HR-shaped table the Admin module administers.
 *
 * `employee_id` is added nullable, backfilled, and only then tightened to
 * `NOT NULL UNIQUE` — the table already has rows, so it cannot be created with
 * that constraint in one step.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_id', 10)->nullable()->after('name');
            $table->string('personal_mobile_no', 11)->nullable()->after('employee_id');
            $table->string('official_mobile_no', 11)->nullable()->after('personal_mobile_no');
            $table->string('official_extension_no', 4)->nullable()->after('official_mobile_no');
            $table->string('gender', 1)->default('M')->after('official_extension_no');
            $table->boolean('approved')->default(true)->after('gender');
            $table->boolean('approval_authority')->default(false)->after('approved');
            $table->foreignId('inserted_by')->nullable()->after('approval_authority')->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->after('inserted_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        $this->backfillEmployeeIds();

        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_id', 10)->nullable(false)->change();

            /*
             * `employee_id` is the login identifier, so every authentication
             * attempt looks it up. The unique constraint supplies that index.
             */
            $table->unique('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['employee_id']);
            $table->dropConstrainedForeignId('inserted_by');
            $table->dropConstrainedForeignId('last_updated_by');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'employee_id',
                'personal_mobile_no',
                'official_mobile_no',
                'official_extension_no',
                'gender',
                'approved',
                'approval_authority',
            ]);
        });
    }

    /**
     * Give every existing row an employee ID so the unique index can be added.
     *
     * The seeded test account gets its real ID; anything else gets a synthetic
     * one derived from the primary key, which is unique by construction.
     */
    private function backfillEmployeeIds(): void
    {
        DB::table('users')->where('email', 'test@example.com')->update(['employee_id' => '15868']);

        DB::table('users')
            ->whereNull('employee_id')
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update(['employee_id' => 'U'.$user->id]);
            });
    }
};
