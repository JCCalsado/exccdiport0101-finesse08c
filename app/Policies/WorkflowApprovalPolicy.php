<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowApproval;

class WorkflowApprovalPolicy
{
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * View the payment approval queue.
     * Disbursing Officer + Admin.
     *
     * Cashier records payments but does NOT approve them.
     * Payment Approval = verifying bank transfer authenticity — a financial
     * authority decision that belongs to the Disbursing Officer.
     */
    public function viewAny(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * View a single approval record.
     */
    public function view(User $user, WorkflowApproval $approval): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * Approve a payment (mark bank transfer as verified/confirmed).
     */
    public function approve(User $user, WorkflowApproval $approval): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * Reject a payment (mark as invalid/mismatch).
     */
    public function reject(User $user, WorkflowApproval $approval): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }
}