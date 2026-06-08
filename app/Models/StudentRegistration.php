<?php

namespace App\Models;

use App\Enums\RegistrationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentRegistration extends Model
{
    protected $fillable = [
        'tracking_token',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'gender',
        'birthdate',
        'civil_status',
        'contact_number',
        'email',
        'address_house',
        'address_street',
        'address_barangay',
        'address_city',
        'address_province',
        'address_zip',
        'existing_student_id',
        'course',
        'year_level',
        'semester',
        'school_year',
        'student_type',
        'guardian_name',
        'guardian_contact',
        'emergency_contact',
        'valid_id_path',
        'proof_of_enrollment_path',
        'password_hash',
        'status',
        // ── Finance stage columns ──────────────────────────────────────────
        'rejection_reason',
        'revision_notes',
        'reviewed_by',
        'reviewed_at',
        // ── Registrar stage columns ────────────────────────────────────────
        'registrar_reviewed_by',
        'registrar_reviewed_at',
        'registrar_rejection_reason',
        'registrar_revision_notes',
        'revision_stage',
        // ──────────────────────────────────────────────────────────────────
        'submitted_at',
        'user_id',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'birthdate'              => 'date',
        'reviewed_at'            => 'datetime',
        'registrar_reviewed_at'  => 'datetime',
        'submitted_at'           => 'datetime',
        'status'                 => RegistrationStatusEnum::class,
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function registrarReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrar_reviewed_by');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->last_name . ',',
            $this->first_name,
            $this->middle_name,
            $this->suffix,
        ]);
        return implode(' ', $parts);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_house,
            $this->address_street,
            $this->address_barangay,
            $this->address_city,
            $this->address_province,
            $this->address_zip,
        ]);
        return implode(', ', $parts);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', RegistrationStatusEnum::PENDING->value);
    }

    public function scopeRegistrarQueue($query)
    {
        return $query->where(function ($q) {
            $q->where('status', RegistrationStatusEnum::PENDING->value)
              ->orWhere(function ($inner) {
                  $inner->where('status', RegistrationStatusEnum::NEEDS_REVISION->value)
                        ->where('revision_stage', 'registrar');
              });
        });
    }

    public function scopeFinanceQueue($query)
    {
        return $query->where(function ($q) {
            $q->where('status', RegistrationStatusEnum::REGISTRAR_CLEARED->value)
              ->orWhere(function ($inner) {
                  $inner->where('status', RegistrationStatusEnum::NEEDS_REVISION->value)
                        ->where('revision_stage', 'finance');
              });
        });
    }

    // ── Status Helpers ────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === RegistrationStatusEnum::PENDING;
    }

    public function isRegistrarCleared(): bool
    {
        return $this->status === RegistrationStatusEnum::REGISTRAR_CLEARED;
    }

    public function isApproved(): bool
    {
        return $this->status === RegistrationStatusEnum::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === RegistrationStatusEnum::REJECTED;
    }

    public function isRejectedByRegistrar(): bool
    {
        return $this->status === RegistrationStatusEnum::REJECTED_BY_REGISTRAR;
    }

    public function needsRevision(): bool
    {
        return $this->status === RegistrationStatusEnum::NEEDS_REVISION;
    }

    public function isRegistrarActionable(): bool
    {
        return $this->status->isRegistrarActionable()
            && ($this->status !== RegistrationStatusEnum::NEEDS_REVISION
                || $this->revision_stage === 'registrar');
    }

    public function isFinanceActionable(): bool
    {
        return $this->status->isFinanceActionable()
            && ($this->status !== RegistrationStatusEnum::NEEDS_REVISION
                || $this->revision_stage === 'finance');
    }

    // ── Duplicate / Conflict Detection ────────────────────────────────────

    public function detectDuplicates(): \Illuminate\Support\Collection
    {
        return static::where('id', '!=', $this->id)
            ->where(function ($q) {
                $q->where('email', $this->email)
                  ->orWhere('contact_number', $this->contact_number)
                  ->orWhere(function ($q2) {
                      $q2->where('last_name', $this->last_name)
                         ->where('first_name', $this->first_name)
                         ->where('birthdate', $this->birthdate);
                  });
            })
            ->whereIn('status', ['pending', 'approved', 'registrar_cleared', 'needs_revision'])
            ->get(['id', 'first_name', 'last_name', 'email', 'contact_number', 'status', 'submitted_at']);
    }

    /**
     * Find a matching User account by email and classify the relationship.
     *
     * Returns an array with:
     *   - user:          the User model instance
     *   - is_same_person: true if last_name + first_name match (case-insensitive)
     *                     → returning student / transferee re-enrolling
     *                     false if name differs
     *                     → genuine email collision, hard block required
     *
     * Returns null if no user with this email exists (normal new-student path).
     */
    public function findMatchingUser(): ?array
    {
        $user = User::where('email', $this->email)->first();

        if (! $user) {
            return null;
        }

        $isSamePerson = mb_strtolower(trim($user->last_name))  === mb_strtolower(trim($this->last_name))
                     && mb_strtolower(trim($user->first_name)) === mb_strtolower(trim($this->first_name));

        return [
            'user'          => $user,
            'is_same_person' => $isSamePerson,
        ];
    }

    // ── Factory ───────────────────────────────────────────────────────────

    public static function generateTrackingToken(): string
    {
        return Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4));
    }
}