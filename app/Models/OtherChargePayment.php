<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherChargePayment extends Model
{
    protected $fillable = [
        'other_charge_id',
        'user_id',
        'amount_paid',
        'payment_method',
        'or_number',
        'paymongo_session_id',
        'payment_intent_id',
        'reference',
        'status',
        'collected_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'paid_at'     => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function charge(): BelongsTo
    {
        return $this->belongsTo(OtherCharge::class, 'other_charge_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'awaiting_confirmation', 'awaiting_proof', 'awaiting_approval']);
    }

    public function scopeAwaitingConfirmation($query)
    {
        return $query->where('status', 'awaiting_confirmation');
    }

    public function scopeOnline($query)
    {
        return $query->where('payment_method', 'online');
    }

    public function scopeOtc($query)
    {
        return $query->where('payment_method', 'otc');
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->payment_method === 'online';
    }

    public function getIsOtcAttribute(): bool
    {
        return $this->payment_method === 'otc';
    }
}
