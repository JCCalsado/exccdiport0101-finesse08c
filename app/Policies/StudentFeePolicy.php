<?php

namespace App\Policies;

use App\Models\User;

class StudentFeePolicy
{
    /**
     * Admin always bypasses. Must be first check in every method.
     */
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * View the assessment list.
     * Disbursing Officer + Cashier (accounting staff who interact with fees).
     * Bookkeeper does NOT see individual fee records — only financial reports.
     */
    public function viewAny(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && $user->isAccounting()
            && ($user->isDisbursingOfficer() || $user->isCashier());
    }

    /**
     * View a single fee assessment record.
     */
    public function view(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && $user->isAccounting()
            && ($user->isDisbursingOfficer() || $user->isCashier());
    }

    /**
     * Create a new fee assessment.
     * Disbursing Officer only.
     */
    public function create(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * Update an existing fee assessment.
     * Disbursing Officer only.
     */
    public function update(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * Delete a fee assessment.
     * Disbursing Officer only.
     */
    public function delete(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * Record a payment against an assessment.
     * Cashier + Disbursing Officer.
     */
    public function recordPayment(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && $user->isAccounting()
            && ($user->isCashier() || $user->isDisbursingOfficer());
    }
}