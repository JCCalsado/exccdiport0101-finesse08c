<?php

namespace App\Policies;

use App\Models\OtherCharge;
use App\Models\User;

/**
 * OtherChargePolicy
 *
 * Role matrix:
 *   admin              → full access
 *   disbursing_officer → full access (create, publish, edit, record OTC payment)
 *   cashier            → view + record OTC payment only
 *   bookkeeper         → view only
 *   student            → view own charges only (handled by Student\OtherChargeController scope)
 */
class OtherChargePolicy
{
    /**
     * View the charge list (accounting side).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'disbursing_officer', 'cashier', 'bookkeeper'])
            || ($user->role === 'accounting' && in_array($user->accounting_type, [
                'disbursing_officer', 'cashier', 'bookkeeper',
            ]));
    }

    /**
     * View a single charge detail page.
     */
    public function view(User $user, OtherCharge $charge): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Create new charges (draft state).
     */
    public function create(User $user): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    /**
     * Edit a charge. Allowed even after publish — UI shows warning when
     * students have already paid.
     */
    public function update(User $user, OtherCharge $charge): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    /**
     * Delete — only drafts. Published charges with payments cannot be deleted.
     */
    public function delete(User $user, OtherCharge $charge): bool
    {
        if (! $this->isDisbursingOfficerOrAdmin($user)) {
            return false;
        }

        // Cannot delete published charges that have payments
        if ($charge->is_published && $charge->payments()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Publish a draft charge to make it visible to students.
     */
    public function publish(User $user, OtherCharge $charge): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    /**
     * Record an OTC payment against a charge on behalf of a student.
     */
    public function recordPayment(User $user, OtherCharge $charge): bool
    {
        return in_array($user->role, ['admin', 'disbursing_officer', 'cashier'])
            || ($user->role === 'accounting' && in_array($user->accounting_type, [
                'disbursing_officer', 'cashier',
            ]));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function isDisbursingOfficerOrAdmin(User $user): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->role === 'disbursing_officer') return true;
        if ($user->role === 'accounting' && $user->accounting_type === 'disbursing_officer') return true;

        return false;
    }
}
