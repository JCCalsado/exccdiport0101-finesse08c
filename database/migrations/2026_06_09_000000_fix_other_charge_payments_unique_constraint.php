<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original constraint was:
     *   UNIQUE ['other_charge_id', 'user_id', 'status']
     *
     * This prevented ANY duplicate of (charge, student, status).
     * It should only enforce ONE record per (charge, student).
     * Multiple attempts (cancelled/pending/paid) should consolidate to one record.
     *
     * Fix: Delete duplicate records, keeping only the most recent one per (charge, student).
     *      Then add a unique constraint on (charge, student).
     */
    public function up(): void
    {
        // Delete duplicate records, keeping only the most recent one per (charge, student)
        $duplicates = DB::select(<<<'SQL'
            SELECT other_charge_id, user_id, MAX(id) as keep_id
            FROM other_charge_payments
            GROUP BY other_charge_id, user_id
            HAVING COUNT(*) > 1
        SQL);

        foreach ($duplicates as $dup) {
            DB::delete(
                'DELETE FROM other_charge_payments WHERE other_charge_id = ? AND user_id = ? AND id != ?',
                [$dup->other_charge_id, $dup->user_id, $dup->keep_id]
            );
        }

        Schema::table('other_charge_payments', function (Blueprint $table) {
            // Add a unique constraint: one record per student per charge
            $table->unique(['other_charge_id', 'user_id'], 'unique_charge_per_student');
        });
    }

    public function down(): void
    {
        Schema::table('other_charge_payments', function (Blueprint $table) {
            $table->dropUnique('unique_charge_per_student');
        });
    }
};
