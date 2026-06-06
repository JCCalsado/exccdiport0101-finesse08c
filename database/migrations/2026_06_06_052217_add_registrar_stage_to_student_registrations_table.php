<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the Registrar stage tracking columns to student_registrations.
     *
     * IMPORTANT: No existing columns are renamed or removed. The existing
     * reviewed_by / reviewed_at / rejection_reason / revision_notes columns
     * are the Finance stage columns and remain exactly as they are.
     *
     * New columns track the Registrar's academic-clearance action separately.
     *
     * revision_stage disambiguates which queue a needs_revision record
     * belongs to — so the student knows whose correction is required.
     *
     * New status values added to application logic (not an ALTER needed — the
     * status column is a VARCHAR, not an ENUM at the DB level):
     *   - 'registrar_cleared'       (passed Registrar, awaiting Finance)
     *   - 'rejected_by_registrar'   (terminal, student must re-apply)
     */
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            // ── Registrar stage reviewer ──────────────────────────────────────
            $table->foreignId('registrar_reviewed_by')
                  ->nullable()
                  ->after('reviewed_at')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('registrar_reviewed_at')
                  ->nullable()
                  ->after('registrar_reviewed_by');

            // ── Registrar stage outcome notes ─────────────────────────────────
            $table->text('registrar_rejection_reason')
                  ->nullable()
                  ->after('registrar_reviewed_at');

            $table->text('registrar_revision_notes')
                  ->nullable()
                  ->after('registrar_rejection_reason');

            // ── Revision stage tracking ───────────────────────────────────────
            // When status = needs_revision, this indicates which queue the
            // record belongs to so the student resubmits to the correct stage.
            // 'registrar' → resubmit goes back to pending (Registrar queue)
            // 'finance'   → resubmit goes back to registrar_cleared (Finance queue)
            $table->enum('revision_stage', ['registrar', 'finance'])
                  ->nullable()
                  ->after('registrar_revision_notes');
        });
    }

    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->dropForeign(['registrar_reviewed_by']);
            $table->dropColumn([
                'registrar_reviewed_by',
                'registrar_reviewed_at',
                'registrar_rejection_reason',
                'registrar_revision_notes',
                'revision_stage',
            ]);
        });
    }
};