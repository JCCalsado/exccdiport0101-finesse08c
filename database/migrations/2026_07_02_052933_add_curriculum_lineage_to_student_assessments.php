<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Curriculum lineage tracking for student_assessments.
 *
 * WHY THIS EXISTS:
 *   Before this migration, an assessment's assessment_subjects snapshot could
 *   be built two ways (AssessmentService::buildSubjectSnapshot() from the
 *   Subject table, or buildSubjectSnapshotFromIds() from a manual selection)
 *   with no record of which path was taken. Nothing on student_assessments
 *   said whether the subject list ever tracked curriculum at all.
 *
 *   generated_from is an immutable *origin* marker — it records how the
 *   assessment's subject snapshot was first built and is never changed by
 *   later edits. It answers "did this assessment ever have a curriculum
 *   link" — not "is it currently in sync." That second question is answered
 *   by comparing the live Subject rows for (course, year_level, semester)
 *   against the stored assessment_subjects snapshot at read time — see
 *   AssessmentService::detectCurriculumDrift().
 *
 *   curriculum_synced_at is a timestamp, not a boolean — it records the last
 *   time the assessment_subjects snapshot was rebuilt from the live
 *   curriculum (via update() when no manual_subject_ids are submitted, or via
 *   the new /curriculum-sync endpoint). It is left null for assessments whose
 *   generated_from = 'manual', since no curriculum sync has ever applied.
 *
 * WHY 'curriculum' IS THE DEFAULT (not 'manual'):
 *   Every assessment created before this migration was built via
 *   buildSubjectSnapshot() unless manual_subject_ids was explicitly submitted.
 *   Backfilling to 'curriculum' matches that historical default and is the
 *   least-wrong assumption for existing rows. It is not retroactively
 *   accurate for the (currently unknowable) subset of legacy assessments
 *   that were in fact created with manual_subject_ids — there is no column
 *   in the old schema that recorded that, so this cannot be reconstructed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            $table->string('generated_from', 20)
                ->default('curriculum')
                ->after('status')
                ->comment("Immutable origin marker: 'curriculum' (buildSubjectSnapshot) or 'manual' (buildSubjectSnapshotFromIds). Set once at creation, never changed by edits.");

            $table->timestamp('curriculum_synced_at')
                ->nullable()
                ->after('generated_from')
                ->comment('Last time assessment_subjects was rebuilt from the live Subject table. Null for manual-origin assessments.');
        });

        // MySQL ENUM would be more restrictive, but per this project's own
        // established pattern (raw DB::statement() required for ENUM changes,
        // see CCDI Portal architectural invariants), a plain VARCHAR + app-level
        // validation is cheaper to evolve if a third origin value is ever needed
        // (e.g. 'imported'). Enforce the two known values at the DB level anyway
        // via a CHECK constraint so this isn't purely an application-layer rule.
        DB::statement("ALTER TABLE student_assessments ADD CONSTRAINT chk_generated_from CHECK (generated_from IN ('curriculum', 'manual'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE student_assessments DROP CONSTRAINT chk_generated_from');

        Schema::table('student_assessments', function (Blueprint $table) {
            $table->dropColumn(['generated_from', 'curriculum_synced_at']);
        });
    }
};