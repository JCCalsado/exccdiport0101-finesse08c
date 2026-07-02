<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * assessment_events — general-purpose audit log for StudentAssessment.
 *
 * WHY A LOG TABLE INSTEAD OF SNAPSHOT VERSIONING:
 *   This is the Executor's plan from the prior council pass, not the
 *   Expansionist's immutable-snapshot-versioning plan. Reasoning:
 *     - assessment_subjects' schema and every read path that queries it
 *       (Show.vue's 'enrolled_subjects', exportPdf, etc.) stay untouched.
 *     - It mirrors an existing, working convention in this codebase
 *       (student_status_logs: entity id, changed_by, reason, action,
 *       indexed timestamps) instead of inventing a new pattern.
 *     - It ships without redesigning the create/edit flow's behavior.
 *   The tradeoff (explicitly accepted, not overlooked): this table tells you
 *   THAT something changed and WHAT the values were, not "reconstruct the
 *   exact assessment_subjects row-set as of version N" the way immutable
 *   snapshot versioning would. For a billing system that already treats
 *   assessment_subjects as a locked-at-creation-time snapshot per assessment
 *   (see assessment_subjects table comment) and blocks all edits the moment
 *   any payment lands, that's sufficient — there is no scenario today where
 *   a mid-lifecycle historical snapshot needs to be reconstructed and shown
 *   on its own, only "what happened and why."
 *
 * event_type values (documented here, not DB-enforced — see reasoning in the
 * generated_from migration for why VARCHAR + app validation over ENUM):
 *   'created'            — assessment first created (fired once, in store())
 *   'subject_added'      — a subject appeared in the new snapshot that wasn't
 *                           in the old one
 *   'subject_removed'    — a subject was in the old snapshot but not the new one
 *   'subject_changed'    — same subject_id present in both, but a snapshot
 *                           field differs (lec_units, lab_units, fees)
 *   'curriculum_synced'  — the /curriculum-sync endpoint was used to rebuild
 *                           the snapshot from live curriculum
 *   'fields_updated'     — non-subject fields changed (semester, school_year,
 *                           discount, etc.) via update()
 *
 * payload holds the old/new values relevant to that event — e.g. for
 * subject_changed: {"subject_id": 12, "code": "CC101", "field": "lec_units",
 * "old": 3, "new": 4}. Kept as a single JSON blob (not per-field columns)
 * because the field set differs per event_type and this table's job is
 * "explain what happened," not "be queried by individual field values."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_assessment_id')
                ->constrained('student_assessments')
                ->cascadeOnDelete();

            $table->string('event_type', 50);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('payload')->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['student_assessment_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_events');
    }
};