<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class OtherCharge extends Model
{
    protected $fillable = [
        'title',
        'description',
        'amount',
        'school_year',
        'semester',
        'year_level',
        'course',
        'created_by',
        'published_at',
        'updated_after_publish_at',
        'is_active',
    ];

    protected $casts = [
        'amount'                   => 'decimal:2',
        'published_at'             => 'datetime',
        'updated_after_publish_at' => 'datetime',
        'is_active'                => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OtherChargePayment::class, 'other_charge_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeDraft($query)
    {
        return $query->whereNull('published_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Computed Accessors ───────────────────────────────────────────────────

    public function getIsPublishedAttribute(): bool
    {
        return $this->published_at !== null;
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->published_at === null;
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) return 'Archived';
        return $this->is_published ? 'Published' : 'Draft';
    }

    // ─── Business Logic ───────────────────────────────────────────────────────

    /**
     * Determine whether this charge applies to a given student.
     *
     * Matching rules (ALL must pass):
     *   school_year   — always required, must match student's active assessment
     *   semester      — if set on charge, must match; null = any semester
     *   year_level    — if set on charge, must match; null = any year level
     *   course        — if set on charge, must match; null = any course
     *
     * The student's active assessment is the source of their current
     * school_year, semester, year_level, and course.
     */
    public function matchesStudent(User $student): bool
    {
        $assessment = $student->studentAssessments()
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $assessment) {
            return false;
        }

        if ($assessment->school_year !== $this->school_year) {
            return false;
        }

        if ($this->semester !== null && $assessment->semester !== $this->semester) {
            return false;
        }

        if ($this->year_level !== null && $assessment->year_level !== $this->year_level) {
            return false;
        }

        if ($this->course !== null && $assessment->course !== $this->course) {
            return false;
        }

        return true;
    }

    /**
     * Check if this charge is owed by a student (matches AND has no paid record).
     */
    public function isOwedByStudent(User $student): bool
    {
        if (! $this->matchesStudent($student)) {
            return false;
        }

        return ! $this->payments()
            ->where('user_id', $student->id)
            ->where('status', 'paid')
            ->exists();
    }

    /**
     * Get the amount still owed by a student for this charge.
     * Returns 0 if already paid, full amount if not.
     * (Full payment only — no partial tracking needed.)
     */
    public function balanceForStudent(User $student): float
    {
        $hasPaid = $this->payments()
            ->where('user_id', $student->id)
            ->where('status', 'paid')
            ->exists();

        return $hasPaid ? 0.0 : (float) $this->amount;
    }

    /**
     * Count of students who match this charge's filters.
     * Joins to student_assessments for filter matching.
     */
    public function matchingStudentCount(): int
    {
        return $this->buildMatchingStudentsQuery()->count();
    }

    /**
     * Query builder for students matching this charge's filters.
     * Used by OtherChargeService::getStudentsForCharge().
     */
    public function buildMatchingStudentsQuery()
    {
        $query = User::where('role', 'student')
            ->whereHas('studentAssessments', function ($q) {
                $q->where('status', 'active')
                  ->where('school_year', $this->school_year);

                if ($this->semester !== null) {
                    $q->where('semester', $this->semester);
                }
                if ($this->year_level !== null) {
                    $q->where('year_level', $this->year_level);
                }
                if ($this->course !== null) {
                    $q->where('course', $this->course);
                }
            });

        return $query;
    }
}
