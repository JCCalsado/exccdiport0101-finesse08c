<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log row for a StudentAssessment.
 *
 * Never updated after creation. Written exclusively via
 * AssessmentService::logEvent() so the payload shape stays consistent
 * across every call site (store(), update(), syncCurriculum()).
 */
class AssessmentEvent extends Model
{
    protected $fillable = [
        'student_assessment_id',
        'event_type',
        'changed_by',
        'payload',
        'reason',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class, 'student_assessment_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}